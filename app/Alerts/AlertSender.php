<?php

namespace App\Alerts;

use App\Mail\IssueAlertMail;
use App\Models\AlertChannel;
use App\Models\AlertRule;
use App\Models\Issue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class AlertSender
{
    public function send(AlertChannel $channel, Issue $issue, ?AlertRule $rule, string $reason, array $flags = []): void
    {
        match ($channel->type) {
            'email' => $this->sendEmail($channel, $issue, $rule, $reason, $flags),
            'telegram' => $this->sendTelegram($channel, $issue, $rule, $reason, $flags),
            default => throw new RuntimeException('Unknown channel type: '.$channel->type),
        };
    }

    public function test(AlertChannel $channel): void
    {
        $issue = new Issue([
            'project_id' => $channel->project_id,
            'fingerprint' => sha1('test'),
            'type' => 'error',
            'level' => 'error',
            'platform' => 'php',
            'title' => 'RuntimeException: This is a test alert from Loggerxo',
            'culprit' => 'app/Http/Controllers/TestController.php:42 in store',
            'status' => 'open',
            'first_seen' => now(),
            'last_seen' => now(),
            'events_count' => 1,
            'environments' => ['production'],
        ]);
        $issue->id = 0;
        $issue->setRelation('project', $channel->project);
        $this->send($channel, $issue, null, 'Test alert', ['environment' => 'production', 'count' => 1]);
    }

    protected function sendEmail(AlertChannel $channel, Issue $issue, ?AlertRule $rule, string $reason, array $flags): void
    {
        $emails = array_values(array_filter(array_map('trim', (array) data_get($channel->config, 'emails', [])), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if ($emails === []) {
            throw new RuntimeException('E-mail channel has no valid recipients');
        }
        Mail::to($emails)->send(new IssueAlertMail($issue, $rule, $reason, $flags));
    }

    protected function sendTelegram(AlertChannel $channel, Issue $issue, ?AlertRule $rule, string $reason, array $flags): void
    {
        $token = data_get($channel->config, 'bot_token') ?: config('loggerxo.telegram_bot_token');
        $chatId = data_get($channel->config, 'chat_id');
        if (! $token || ! $chatId) {
            throw new RuntimeException('Telegram channel needs a bot token and a chat id');
        }
        $project = $issue->project;
        $env = $flags['environment'] ?? ($issue->environments[0] ?? 'production');
        $url = $issue->id ? $issue->url() : rtrim(config('app.url'), '/');
        $lines = [
            sprintf('%s <b>%s</b> · %s', $this->emoji($issue->level, $reason), e($reason), e($project?->name ?? 'project')),
            '',
            '<b>'.e(mb_substr($issue->title, 0, 300)).'</b>',
        ];
        if ($issue->culprit) {
            $lines[] = '<code>'.e(mb_substr($issue->culprit, 0, 200)).'</code>';
        }
        $lines[] = '';
        $lines[] = sprintf('Level: <b>%s</b> · Env: <b>%s</b> · Events: <b>%s</b>', e($issue->level), e($env), number_format($issue->events_count));
        $lines[] = '<a href="'.e($url).'">Open in Loggerxo</a>';

        $response = Http::timeout(10)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Telegram API: '.mb_substr($response->body(), 0, 300));
        }
    }

    protected function emoji(string $level, string $reason): string
    {
        if (str_contains($reason, 'Regression')) {
            return '🔁';
        }
        if (str_contains($reason, 'Spike')) {
            return '📈';
        }

        return match ($level) {
            'emergency', 'alert', 'critical' => '🚨',
            'error' => '🔴',
            'warning' => '🟠',
            default => '🔵',
        };
    }
}
