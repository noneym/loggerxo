<?php

namespace App\Ingest;

use App\Jobs\EvaluateIssueAlerts;
use App\Models\AlertRule;
use App\Models\Project;
use App\Services\ClickHouse\ClickHouseClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Processes a batch of raw queue items: normalize -> group issues -> ClickHouse insert -> alerts.
 */
class IngestProcessor
{
    public function __construct(
        protected ClickHouseClient $clickhouse,
        protected EventNormalizer $normalizer,
        protected IssueAggregator $aggregator,
    ) {}

    public static function make(): static
    {
        return new static(ClickHouseClient::fromConfig(), EventNormalizer::make(), new IssueAggregator);
    }

    /**
     * @param  array<int, array{meta: array, event: array}>  $items
     * @return array{events: int, transactions: int, skipped: int, issues: int}
     */
    public function process(array $items): array
    {
        $eventRows = [];
        $transactionRows = [];
        $issueInputs = [];
        $skipped = 0;
        $touchedProjects = [];

        foreach ($items as $item) {
            $meta = $item['meta'] ?? null;
            $event = $item['event'] ?? null;
            if (! is_array($meta) || ! is_array($event) || empty($meta['project_id'])) {
                $skipped++;

                continue;
            }
            try {
                $normalized = $this->normalizer->normalize($event, $meta);
            } catch (\Throwable $e) {
                Log::warning('loggerxo: failed to normalize event', ['error' => $e->getMessage()]);
                $normalized = null;
            }
            if (! $normalized) {
                $skipped++;

                continue;
            }
            $touchedProjects[(int) $meta['project_id']] = true;
            if ($normalized['table'] === 'transactions') {
                $transactionRows[] = $normalized['row'];

                continue;
            }
            $issueInput = $normalized['issue'] + ['project_id' => (int) $meta['project_id']];
            $issueInputs[] = $issueInput;
            $eventRows[] = $normalized['row'];
        }

        $issueResults = $issueInputs !== [] ? $this->aggregator->aggregate($issueInputs) : [];

        foreach ($eventRows as &$row) {
            $key = $row['project_id'].':'.$row['fingerprint'];
            $row['issue_id'] = $issueResults[$key]['issue']->id ?? 0;
        }
        unset($row);

        if ($eventRows !== []) {
            $this->clickhouse->insert($this->clickhouse->table('events'), $eventRows);
        }
        if ($transactionRows !== []) {
            $this->clickhouse->insert($this->clickhouse->table('transactions'), $transactionRows);
        }

        $this->dispatchAlerts($issueResults);
        $this->touchProjects(array_keys($touchedProjects));

        return [
            'events' => count($eventRows),
            'transactions' => count($transactionRows),
            'skipped' => $skipped,
            'issues' => count($issueResults),
        ];
    }

    protected function dispatchAlerts(array $issueResults): void
    {
        foreach ($issueResults as $result) {
            $issue = $result['issue'];
            if (! $this->projectHasRules($issue->project_id)) {
                continue;
            }
            EvaluateIssueAlerts::dispatch($issue->id, [
                'new' => $result['new'],
                'regressed' => $result['regressed'],
                'count' => $result['count'],
                'level' => $result['level'],
                'environment' => $result['environment'],
            ])->onQueue('alerts');
        }
    }

    protected function projectHasRules(int $projectId): bool
    {
        return (bool) Cache::remember('lx:has-rules:'.$projectId, 60, fn () => AlertRule::where('project_id', $projectId)->where('is_active', true)->exists());
    }

    protected function touchProjects(array $projectIds): void
    {
        foreach ($projectIds as $projectId) {
            if (Cache::add('lx:touched:'.$projectId, 1, 60)) {
                Project::whereKey($projectId)->update(['last_event_at' => now()]);
            }
        }
    }
}
