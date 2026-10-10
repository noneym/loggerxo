<?php

namespace App\Alerts;

use App\Models\AlertChannel;
use App\Models\AlertNotification;
use App\Models\AlertRule;
use App\Models\Issue;
use App\Services\ClickHouse\ClickHouseClient;
use App\Support\Levels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertDispatcher
{
    public function __construct(protected AlertSender $sender) {}

    /**
     * @param  array{new: bool, regressed: bool, count: int, level: string, environment: string}  $flags
     */
    public function evaluate(Issue $issue, array $flags): void
    {
        if ($issue->status === Issue::STATUS_IGNORED) {
            return;
        }

        $rules = AlertRule::where('project_id', $issue->project_id)->where('is_active', true)->get();
        if ($rules->isEmpty()) {
            return;
        }

        $channels = AlertChannel::where('project_id', $issue->project_id)->where('is_active', true)->get()->keyBy('id');

        foreach ($rules as $rule) {
            if (! $this->matches($rule, $issue, $flags)) {
                continue;
            }
            $cooldownKey = sprintf('lx:alert-cooldown:%d:%d', $rule->id, $issue->id);
            if (! Cache::add($cooldownKey, 1, max(1, $rule->cooldown_minutes) * 60)) {
                continue;
            }
            $reason = $this->reason($rule, $flags);
            $sentAny = false;
            foreach ((array) $rule->channel_ids as $channelId) {
                $channel = $channels->get((int) $channelId);
                if (! $channel) {
                    continue;
                }
                $notification = new AlertNotification([
                    'project_id' => $issue->project_id,
                    'alert_rule_id' => $rule->id,
                    'alert_channel_id' => $channel->id,
                    'issue_id' => $issue->id,
                    'channel_type' => $channel->type,
                    'subject' => mb_substr($reason.': '.$issue->title, 0, 300),
                ]);
                try {
                    $this->sender->send($channel, $issue, $rule, $reason, $flags);
                    $notification->status = 'sent';
                    $channel->forceFill(['last_used_at' => now(), 'last_error' => null])->save();
                    $sentAny = true;
                } catch (\Throwable $e) {
                    Log::warning('loggerxo: alert delivery failed', ['channel' => $channel->id, 'error' => $e->getMessage()]);
                    $notification->status = 'failed';
                    $notification->error = mb_substr($e->getMessage(), 0, 500);
                    $channel->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
                }
                $notification->save();
            }
            if ($sentAny) {
                $rule->forceFill(['last_triggered_at' => now()])->save();
            }
        }
    }

    protected function matches(AlertRule $rule, Issue $issue, array $flags): bool
    {
        $envs = array_values(array_filter((array) $rule->condition('environments', [])));
        if ($envs !== [] && ! in_array($flags['environment'] ?? 'production', $envs, true)) {
            return false;
        }
        $types = array_values(array_filter((array) $rule->condition('types', [])));
        if ($types !== [] && ! in_array($issue->type, $types, true)) {
            return false;
        }

        return match ($rule->trigger) {
            'new_issue' => (bool) ($flags['new'] ?? false),
            'regression' => (bool) ($flags['regressed'] ?? false),
            'log_level' => Levels::atLeast($flags['level'] ?? $issue->level, (string) $rule->condition('min_level', 'critical')),
            'frequency' => $this->frequencyExceeded($rule, $issue),
            default => false,
        };
    }

    protected function frequencyExceeded(AlertRule $rule, Issue $issue): bool
    {
        $threshold = max(1, (int) $rule->condition('threshold', 10));
        $window = max(1, (int) $rule->condition('window_minutes', 5));
        try {
            $ch = ClickHouseClient::fromConfig();
            $count = (int) $ch->scalar(
                sprintf('SELECT count() FROM %s WHERE project_id = {pid:UInt64} AND issue_id = {iid:UInt64} AND timestamp >= now64(3) - INTERVAL {w:UInt32} MINUTE', $ch->table('events')),
                ['pid' => $issue->project_id, 'iid' => $issue->id, 'w' => $window],
                0
            );
        } catch (\Throwable $e) {
            Log::warning('loggerxo: frequency check failed', ['error' => $e->getMessage()]);

            return false;
        }

        return $count >= $threshold;
    }

    protected function reason(AlertRule $rule, array $flags): string
    {
        return match ($rule->trigger) {
            'new_issue' => 'New issue',
            'regression' => 'Regression',
            'frequency' => 'Spike',
            'log_level' => strtoupper((string) ($flags['level'] ?? 'error')).' event',
            default => 'Alert',
        };
    }
}
