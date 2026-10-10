<?php

namespace App\Services;

use App\Models\Project;
use App\Services\ClickHouse\ClickHouseClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * All dashboard-side ClickHouse queries live here.
 */
class EventRepository
{
    public const RANGES = [
        '1h' => ['label' => 'Last hour', 'seconds' => 3600, 'bucket' => 60],
        '6h' => ['label' => 'Last 6 hours', 'seconds' => 21600, 'bucket' => 300],
        '24h' => ['label' => 'Last 24 hours', 'seconds' => 86400, 'bucket' => 3600],
        '7d' => ['label' => 'Last 7 days', 'seconds' => 604800, 'bucket' => 21600],
        '30d' => ['label' => 'Last 30 days', 'seconds' => 2592000, 'bucket' => 86400],
    ];

    public function __construct(protected ClickHouseClient $ch) {}

    public static function make(): static
    {
        return new static(app(ClickHouseClient::class));
    }

    public static function rangeOptions(): array
    {
        return array_map(fn ($r) => $r['label'], self::RANGES);
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, bucket: int, key: string}
     */
    public function range(string $key): array
    {
        $def = self::RANGES[$key] ?? self::RANGES['24h'];
        $to = CarbonImmutable::now('UTC');

        return ['from' => $to->subSeconds($def['seconds']), 'to' => $to, 'bucket' => $def['bucket'], 'key' => self::RANGES[$key] ? $key : '24h'];
    }

    protected function events(): string
    {
        return $this->ch->table('events');
    }

    protected function transactions(): string
    {
        return $this->ch->table('transactions');
    }

    /* ------------------------------------------------------------- series */

    /**
     * Event counts per bucket, split by type. Returns ['labels' => [...], 'series' => [type => [..]]].
     */
    public function eventSeries(Project $project, string $rangeKey, array $filters = []): array
    {
        $r = $this->range($rangeKey);
        [$where, $params] = $this->eventFilters($project, $filters, $r);
        $rows = $this->ch->select(
            "SELECT toStartOfInterval(timestamp, INTERVAL {bucket:UInt32} SECOND) AS t, type, count() AS c
             FROM {$this->events()} WHERE {$where}
             GROUP BY t, type ORDER BY t",
            $params + ['bucket' => $r['bucket']]
        );
        $types = $filters['types'] ?? ['error', 'log', 'console'];

        return $this->fillSeries($rows, $r, $types, 'type');
    }

    public function issueSeries(Project $project, int $issueId, string $rangeKey): array
    {
        $r = $this->range($rangeKey);
        $rows = $this->ch->select(
            "SELECT toStartOfInterval(timestamp, INTERVAL {bucket:UInt32} SECOND) AS t, 'events' AS k, count() AS c
             FROM {$this->events()} WHERE project_id = {pid:UInt64} AND issue_id = {iid:UInt64} AND timestamp >= {from:DateTime64(3)}
             GROUP BY t ORDER BY t",
            ['pid' => $project->id, 'iid' => $issueId, 'from' => $r['from'], 'bucket' => $r['bucket']]
        );

        return $this->fillSeries($rows, $r, ['events'], 'k');
    }

    /**
     * Sparklines for a list of issues: [issue_id => [counts...]] with 24 buckets over the range.
     */
    public function issueSparklines(Project $project, array $issueIds, string $rangeKey = '24h'): array
    {
        if ($issueIds === []) {
            return [];
        }
        $r = $this->range($rangeKey);
        $bucket = max(60, intdiv($r['to']->getTimestamp() - $r['from']->getTimestamp(), 24));
        $rows = $this->ch->select(
            "SELECT issue_id, toStartOfInterval(timestamp, INTERVAL {bucket:UInt32} SECOND) AS t, count() AS c
             FROM {$this->events()} WHERE project_id = {pid:UInt64} AND issue_id IN {ids:Array(UInt64)} AND timestamp >= {from:DateTime64(3)}
             GROUP BY issue_id, t",
            ['pid' => $project->id, 'ids' => array_map('intval', $issueIds), 'from' => $r['from'], 'bucket' => $bucket]
        );
        $start = intdiv($r['from']->getTimestamp(), $bucket) * $bucket;
        $out = array_fill_keys(array_map('intval', $issueIds), array_fill(0, 24, 0));
        foreach ($rows as $row) {
            $idx = intdiv(strtotime($row['t'].' UTC') - $start, $bucket);
            if ($idx >= 0 && $idx < 24) {
                $out[(int) $row['issue_id']][$idx] += (int) $row['c'];
            }
        }

        return $out;
    }

    protected function fillSeries(array $rows, array $r, array $keys, string $keyColumn): array
    {
        $bucket = $r['bucket'];
        $start = intdiv($r['from']->getTimestamp(), $bucket) * $bucket;
        $end = $r['to']->getTimestamp();
        $labels = [];
        $index = [];
        $i = 0;
        for ($t = $start; $t <= $end; $t += $bucket) {
            $labels[] = $this->label($t, $bucket);
            $index[$t] = $i++;
        }
        $series = [];
        foreach ($keys as $k) {
            $series[$k] = array_fill(0, count($labels), 0);
        }
        foreach ($rows as $row) {
            $t = strtotime($row['t'].' UTC');
            $k = $row[$keyColumn];
            if (isset($index[$t]) && isset($series[$k])) {
                $series[$k][$index[$t]] = (int) $row['c'];
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    protected function label(int $ts, int $bucket): string
    {
        return $bucket >= 86400 ? gmdate('M j', $ts) : ($bucket >= 3600 ? gmdate('M j H:i', $ts) : gmdate('H:i', $ts));
    }

    /* ------------------------------------------------------------ summary */

    public function projectSummary(Project $project, string $rangeKey): array
    {
        $r = $this->range($rangeKey);
        $params = ['pid' => $project->id, 'from' => $r['from']];
        $counts = $this->ch->selectOne(
            "SELECT countIf(type = 'error') AS errors, countIf(type = 'log') AS logs, countIf(type = 'console') AS console,
                    uniqIf(user_id, user_id != '') AS users, uniq(issue_id) AS issues
             FROM {$this->events()} WHERE project_id = {pid:UInt64} AND timestamp >= {from:DateTime64(3)}",
            $params
        ) ?? [];
        $tx = $this->ch->selectOne(
            "SELECT count() AS transactions, quantile(0.95)(duration_ms) AS p95, quantile(0.5)(duration_ms) AS p50,
                    countIf(status_code >= 500) AS failed
             FROM {$this->transactions()} WHERE project_id = {pid:UInt64} AND timestamp >= {from:DateTime64(3)}",
            $params
        ) ?? [];

        return [
            'errors' => (int) ($counts['errors'] ?? 0),
            'logs' => (int) ($counts['logs'] ?? 0),
            'console' => (int) ($counts['console'] ?? 0),
            'users' => (int) ($counts['users'] ?? 0),
            'issues' => (int) ($counts['issues'] ?? 0),
            'transactions' => (int) ($tx['transactions'] ?? 0),
            'p95' => round((float) ($tx['p95'] ?? 0)),
            'p50' => round((float) ($tx['p50'] ?? 0)),
            'failed' => (int) ($tx['failed'] ?? 0),
        ];
    }

    /**
     * Per-project counts for the organization dashboard: [project_id => [errors, events, transactions]].
     */
    public function organizationSummary(array $projectIds, string $rangeKey = '24h'): array
    {
        if ($projectIds === []) {
            return [];
        }
        $r = $this->range($rangeKey);
        $params = ['ids' => array_map('intval', $projectIds), 'from' => $r['from']];
        $out = array_fill_keys(array_map('intval', $projectIds), ['errors' => 0, 'events' => 0, 'transactions' => 0, 'p95' => 0]);
        foreach ($this->ch->select("SELECT project_id, countIf(type = 'error') AS errors, count() AS events FROM {$this->events()} WHERE project_id IN {ids:Array(UInt64)} AND timestamp >= {from:DateTime64(3)} GROUP BY project_id", $params) as $row) {
            $out[(int) $row['project_id']]['errors'] = (int) $row['errors'];
            $out[(int) $row['project_id']]['events'] = (int) $row['events'];
        }
        foreach ($this->ch->select("SELECT project_id, count() AS c, quantile(0.95)(duration_ms) AS p95 FROM {$this->transactions()} WHERE project_id IN {ids:Array(UInt64)} AND timestamp >= {from:DateTime64(3)} GROUP BY project_id", $params) as $row) {
            $out[(int) $row['project_id']]['transactions'] = (int) $row['c'];
            $out[(int) $row['project_id']]['p95'] = round((float) $row['p95']);
        }

        return $out;
    }

    public function organizationSeries(array $projectIds, string $rangeKey = '24h'): array
    {
        $r = $this->range($rangeKey);
        if ($projectIds === []) {
            return $this->fillSeries([], $r, ['error', 'log', 'console'], 'type');
        }
        $rows = $this->ch->select(
            "SELECT toStartOfInterval(timestamp, INTERVAL {bucket:UInt32} SECOND) AS t, type, count() AS c
             FROM {$this->events()} WHERE project_id IN {ids:Array(UInt64)} AND timestamp >= {from:DateTime64(3)}
             GROUP BY t, type ORDER BY t",
            ['ids' => array_map('intval', $projectIds), 'from' => $r['from'], 'bucket' => $r['bucket']]
        );

        return $this->fillSeries($rows, $r, ['error', 'log', 'console'], 'type');
    }

    /* ------------------------------------------------------------- events */

    /**
     * @param  array  $filters  types[], levels[], environment, search, issue_id, logger, user, release, before (cursor "ts|event_id")
     */
    public function listEvents(Project $project, array $filters, string $rangeKey, int $limit = 100): array
    {
        $r = $this->range($rangeKey);
        [$where, $params] = $this->eventFilters($project, $filters, $r);
        if (! empty($filters['before'])) {
            [$ts, $id] = array_pad(explode('|', $filters['before'], 2), 2, '');
            $where .= ' AND (timestamp, toString(event_id)) < ({cts:DateTime64(3)}, {cid:String})';
            $params['cts'] = $ts;
            $params['cid'] = $id;
        }
        $rows = $this->ch->select(
            "SELECT toString(event_id) AS event_id, type, level, timestamp, environment, release, platform, logger, message,
                    exception_type, exception_value, issue_id, transaction, url, http_method, status_code, user_id, user_email,
                    browser, os, device, server_name, tags
             FROM {$this->events()} WHERE {$where}
             ORDER BY timestamp DESC, event_id DESC LIMIT {lim:UInt32}",
            $params + ['lim' => $limit + 1]
        );
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $last = $rows !== [] ? end($rows) : null;

        return [
            'rows' => $rows,
            'has_more' => $hasMore,
            'next' => $last ? $last['timestamp'].'|'.$last['event_id'] : null,
        ];
    }

    public function countEvents(Project $project, array $filters, string $rangeKey): int
    {
        $r = $this->range($rangeKey);
        [$where, $params] = $this->eventFilters($project, $filters, $r);

        return (int) $this->ch->scalar("SELECT count() FROM {$this->events()} WHERE {$where}", $params, 0);
    }

    public function getEvent(Project $project, string $eventId): ?array
    {
        // Alias must not be "event_id": ClickHouse would resolve the WHERE against the String alias.
        $row = $this->ch->selectOne(
            "SELECT toString(event_id) AS id, * EXCEPT event_id FROM {$this->events()}
             WHERE project_id = {pid:UInt64} AND event_id = {eid:UUID} LIMIT 1",
            ['pid' => $project->id, 'eid' => $eventId]
        );

        return $row ? $this->decode($row) : null;
    }

    public function latestIssueEvent(Project $project, int $issueId): ?array
    {
        $row = $this->ch->selectOne(
            "SELECT toString(event_id) AS id, * EXCEPT event_id FROM {$this->events()}
             WHERE project_id = {pid:UInt64} AND issue_id = {iid:UInt64} ORDER BY timestamp DESC LIMIT 1",
            ['pid' => $project->id, 'iid' => $issueId]
        );

        return $row ? $this->decode($row) : null;
    }

    /**
     * @return array{older: ?string, newer: ?string, position: int, total: int}
     */
    public function issueEventNeighbours(Project $project, int $issueId, array $event): array
    {
        $params = ['pid' => $project->id, 'iid' => $issueId, 'ts' => $event['timestamp'], 'eid' => $event['event_id']];
        $older = $this->ch->scalar(
            "SELECT toString(event_id) FROM {$this->events()} WHERE project_id = {pid:UInt64} AND issue_id = {iid:UInt64}
             AND (timestamp, toString(event_id)) < ({ts:DateTime64(3)}, {eid:String}) ORDER BY timestamp DESC, event_id DESC LIMIT 1",
            $params
        );
        $newer = $this->ch->scalar(
            "SELECT toString(event_id) FROM {$this->events()} WHERE project_id = {pid:UInt64} AND issue_id = {iid:UInt64}
             AND (timestamp, toString(event_id)) > ({ts:DateTime64(3)}, {eid:String}) ORDER BY timestamp ASC, event_id ASC LIMIT 1",
            $params
        );

        return ['older' => $older ?: null, 'newer' => $newer ?: null];
    }

    public function issueFacets(Project $project, int $issueId, string $rangeKey): array
    {
        $r = $this->range($rangeKey);
        $params = ['pid' => $project->id, 'iid' => $issueId, 'from' => $r['from']];
        $facets = [];
        foreach (['environment', 'browser', 'os', 'release', 'url', 'server_name', 'user_email'] as $column) {
            $rows = $this->ch->select(
                "SELECT {$column} AS v, count() AS c FROM {$this->events()}
                 WHERE project_id = {pid:UInt64} AND issue_id = {iid:UInt64} AND timestamp >= {from:DateTime64(3)} AND {$column} != ''
                 GROUP BY v ORDER BY c DESC LIMIT 6",
                $params
            );
            if ($rows !== []) {
                $facets[$column] = $rows;
            }
        }
        $users = $this->ch->scalar("SELECT uniqIf(user_id, user_id != '') FROM {$this->events()} WHERE project_id = {pid:UInt64} AND issue_id = {iid:UInt64} AND timestamp >= {from:DateTime64(3)}", $params, 0);

        return ['facets' => $facets, 'users' => (int) $users];
    }

    /**
     * Distinct values of a low-cardinality column for filter dropdowns (cached).
     */
    public function distinct(Project $project, string $column, int $days = 7): array
    {
        $allowed = ['environment', 'logger', 'release', 'server_name', 'browser', 'os'];
        if (! in_array($column, $allowed, true)) {
            return [];
        }

        return Cache::remember("lx:distinct:{$project->id}:{$column}", 120, function () use ($project, $column, $days) {
            $rows = $this->ch->select(
                "SELECT {$column} AS v, count() AS c FROM {$this->events()}
                 WHERE project_id = {pid:UInt64} AND timestamp >= now64(3) - INTERVAL {d:UInt16} DAY AND {$column} != ''
                 GROUP BY v ORDER BY c DESC LIMIT 50",
                ['pid' => $project->id, 'd' => $days]
            );

            return array_column($rows, 'v');
        });
    }

    protected function eventFilters(Project $project, array $filters, array $r): array
    {
        $where = ['project_id = {pid:UInt64}', 'timestamp >= {from:DateTime64(3)}'];
        $params = ['pid' => $project->id, 'from' => $r['from']];

        if (! empty($filters['types'])) {
            $where[] = 'type IN {types:Array(String)}';
            $params['types'] = array_values($filters['types']);
        }
        if (! empty($filters['levels'])) {
            $where[] = 'level IN {levels:Array(String)}';
            $params['levels'] = array_values($filters['levels']);
        }
        if (! empty($filters['environment'])) {
            $where[] = 'environment = {env:String}';
            $params['env'] = $filters['environment'];
        }
        if (! empty($filters['logger'])) {
            $where[] = 'logger = {logger:String}';
            $params['logger'] = $filters['logger'];
        }
        if (! empty($filters['release'])) {
            $where[] = 'release = {rel:String}';
            $params['rel'] = $filters['release'];
        }
        if (! empty($filters['issue_id'])) {
            $where[] = 'issue_id = {iid:UInt64}';
            $params['iid'] = (int) $filters['issue_id'];
        }
        if (! empty($filters['user'])) {
            $where[] = '(user_id = {user:String} OR user_email = {user:String} OR user_ip = {user:String})';
            $params['user'] = $filters['user'];
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            if (preg_match('/^([a-z_]+):(.+)$/i', $search, $m) && in_array(strtolower($m[1]), ['url', 'transaction', 'server', 'user', 'tag', 'exception', 'release', 'logger'], true)) {
                $col = match (strtolower($m[1])) {
                    'server' => 'server_name',
                    'user' => 'user_email',
                    'exception' => 'exception_type',
                    default => strtolower($m[1]),
                };
                if ($col === 'tag' && str_contains($m[2], '=')) {
                    [$tk, $tv] = explode('=', $m[2], 2);
                    $where[] = 'tags[{tk:String}] = {tv:String}';
                    $params['tk'] = trim($tk);
                    $params['tv'] = trim($tv);
                } else {
                    $where[] = "positionCaseInsensitive({$col}, {q:String}) > 0";
                    $params['q'] = trim($m[2]);
                }
            } else {
                $where[] = '(positionCaseInsensitive(message, {q:String}) > 0 OR positionCaseInsensitive(exception_value, {q:String}) > 0 OR positionCaseInsensitive(url, {q:String}) > 0 OR positionCaseInsensitive(transaction, {q:String}) > 0)';
                $params['q'] = $search;
            }
        }

        return [implode(' AND ', $where), $params];
    }

    public function decode(array $row): array
    {
        if (isset($row['id']) && ! isset($row['event_id'])) {
            $row = ['event_id' => $row['id']] + $row;
            unset($row['id']);
        }
        $payload = json_decode($row['payload'] ?? '{}', true) ?: [];
        unset($row['payload']);
        $row['payload'] = $payload;
        if (isset($row['tags']) && is_string($row['tags'])) {
            $row['tags'] = json_decode($row['tags'], true) ?: [];
        }

        return $row;
    }

    /* ------------------------------------------------------- transactions */

    public function transactionGroups(Project $project, string $rangeKey, array $filters = [], string $sort = 'p95', int $limit = 50): array
    {
        $r = $this->range($rangeKey);
        $where = ['project_id = {pid:UInt64}', 'timestamp >= {from:DateTime64(3)}'];
        $params = ['pid' => $project->id, 'from' => $r['from']];
        if (! empty($filters['op'])) {
            $where[] = 'op = {op:String}';
            $params['op'] = $filters['op'];
        }
        if (! empty($filters['environment'])) {
            $where[] = 'environment = {env:String}';
            $params['env'] = $filters['environment'];
        }
        if (! empty($filters['search'])) {
            $where[] = 'positionCaseInsensitive(name, {q:String}) > 0';
            $params['q'] = $filters['search'];
        }
        $orderBy = match ($sort) {
            'count' => 'count DESC',
            'p50' => 'p50 DESC',
            'failures' => 'failure_rate DESC, count DESC',
            'total' => 'total_ms DESC',
            default => 'p95 DESC',
        };
        $whereSql = implode(' AND ', $where);

        return $this->ch->select(
            "SELECT name, any(op) AS op, any(http_method) AS http_method, count() AS count,
                    quantile(0.5)(duration_ms) AS p50, quantile(0.95)(duration_ms) AS p95, max(duration_ms) AS max,
                    avg(duration_ms) AS avg, sum(duration_ms) AS total_ms,
                    countIf(status_code >= 500) / count() AS failure_rate,
                    avg(db_time_ms) AS db_avg, avg(db_query_count) AS queries_avg
             FROM {$this->transactions()} WHERE {$whereSql}
             GROUP BY name ORDER BY {$orderBy} LIMIT {lim:UInt32}",
            $params + ['lim' => $limit]
        );
    }

    public function transactionSeries(Project $project, string $rangeKey, ?string $name = null): array
    {
        $r = $this->range($rangeKey);
        $params = ['pid' => $project->id, 'from' => $r['from'], 'bucket' => $r['bucket']];
        $nameSql = '';
        if ($name !== null) {
            $nameSql = ' AND name = {name:String}';
            $params['name'] = $name;
        }
        $rows = $this->ch->select(
            "SELECT toStartOfInterval(timestamp, INTERVAL {bucket:UInt32} SECOND) AS t, count() AS c,
                    quantile(0.5)(duration_ms) AS p50, quantile(0.95)(duration_ms) AS p95, countIf(status_code >= 500) AS failed
             FROM {$this->transactions()} WHERE project_id = {pid:UInt64} AND timestamp >= {from:DateTime64(3)}{$nameSql}
             GROUP BY t ORDER BY t",
            $params
        );
        $bucket = $r['bucket'];
        $start = intdiv($r['from']->getTimestamp(), $bucket) * $bucket;
        $labels = [];
        $index = [];
        $i = 0;
        for ($t = $start; $t <= $r['to']->getTimestamp(); $t += $bucket) {
            $labels[] = $this->label($t, $bucket);
            $index[$t] = $i++;
        }
        $n = count($labels);
        $series = ['count' => array_fill(0, $n, 0), 'p50' => array_fill(0, $n, 0), 'p95' => array_fill(0, $n, 0), 'failed' => array_fill(0, $n, 0)];
        foreach ($rows as $row) {
            $t = strtotime($row['t'].' UTC');
            if (! isset($index[$t])) {
                continue;
            }
            $series['count'][$index[$t]] = (int) $row['c'];
            $series['p50'][$index[$t]] = round((float) $row['p50']);
            $series['p95'][$index[$t]] = round((float) $row['p95']);
            $series['failed'][$index[$t]] = (int) $row['failed'];
        }

        return ['labels' => $labels, 'series' => $series];
    }

    public function transactionStats(Project $project, string $rangeKey, string $name): array
    {
        $r = $this->range($rangeKey);
        $row = $this->ch->selectOne(
            "SELECT count() AS count, quantile(0.5)(duration_ms) AS p50, quantile(0.95)(duration_ms) AS p95, quantile(0.99)(duration_ms) AS p99,
                    avg(duration_ms) AS avg, max(duration_ms) AS max, countIf(status_code >= 500) AS failed,
                    avg(db_time_ms) AS db_avg, avg(db_query_count) AS queries_avg, avg(memory_mb) AS memory_avg,
                    uniqIf(user_id, user_id != '') AS users, any(op) AS op
             FROM {$this->transactions()} WHERE project_id = {pid:UInt64} AND name = {name:String} AND timestamp >= {from:DateTime64(3)}",
            ['pid' => $project->id, 'name' => $name, 'from' => $r['from']]
        ) ?? [];
        $measurements = $this->ch->select(
            "SELECT k, avg(v) AS avg, quantile(0.75)(v) AS p75 FROM {$this->transactions()} ARRAY JOIN mapKeys(measurements) AS k, mapValues(measurements) AS v
             WHERE project_id = {pid:UInt64} AND name = {name:String} AND timestamp >= {from:DateTime64(3)} GROUP BY k ORDER BY k",
            ['pid' => $project->id, 'name' => $name, 'from' => $r['from']]
        );

        return ['stats' => $row, 'measurements' => $measurements];
    }

    public function transactionSamples(Project $project, string $rangeKey, string $name, string $sort = 'slowest', int $limit = 25): array
    {
        $r = $this->range($rangeKey);
        $order = $sort === 'latest' ? 'timestamp DESC' : ($sort === 'failed' ? 'status_code DESC, timestamp DESC' : 'duration_ms DESC');

        return $this->ch->select(
            "SELECT toString(event_id) AS event_id, timestamp, duration_ms, status_code, http_method, environment, release, url, user_id, browser, os, db_time_ms, db_query_count, memory_mb, server_name
             FROM {$this->transactions()} WHERE project_id = {pid:UInt64} AND name = {name:String} AND timestamp >= {from:DateTime64(3)}
             ORDER BY {$order} LIMIT {lim:UInt32}",
            ['pid' => $project->id, 'name' => $name, 'from' => $r['from'], 'lim' => $limit]
        );
    }

    public function getTransaction(Project $project, string $eventId): ?array
    {
        $row = $this->ch->selectOne(
            "SELECT toString(event_id) AS id, * EXCEPT event_id FROM {$this->transactions()} WHERE project_id = {pid:UInt64} AND event_id = {eid:UUID} LIMIT 1",
            ['pid' => $project->id, 'eid' => $eventId]
        );

        return $row ? $this->decode($row) : null;
    }

    public function webVitals(Project $project, string $rangeKey): ?array
    {
        $r = $this->range($rangeKey);
        $row = $this->ch->selectOne(
            "SELECT count() AS pageloads,
                    quantile(0.75)(measurements['lcp_ms']) AS lcp, quantile(0.75)(measurements['fcp_ms']) AS fcp,
                    quantile(0.75)(measurements['ttfb_ms']) AS ttfb, quantile(0.75)(measurements['cls']) AS cls,
                    quantile(0.75)(duration_ms) AS load
             FROM {$this->transactions()} WHERE project_id = {pid:UInt64} AND op = 'pageload' AND timestamp >= {from:DateTime64(3)}",
            ['pid' => $project->id, 'from' => $r['from']]
        );
        if (! $row || (int) $row['pageloads'] === 0) {
            return null;
        }

        return $row;
    }

    public function slowQueries(Project $project, string $rangeKey, int $limit = 15): array
    {
        $r = $this->range($rangeKey);

        return $this->ch->select(
            "SELECT JSONExtractString(span, 'description') AS query, count() AS count, avg(JSONExtractFloat(span, 'duration_ms')) AS avg_ms, max(JSONExtractFloat(span, 'duration_ms')) AS max_ms
             FROM {$this->transactions()} ARRAY JOIN JSONExtractArrayRaw(payload, 'spans') AS span
             WHERE project_id = {pid:UInt64} AND timestamp >= {from:DateTime64(3)} AND JSONExtractString(span, 'op') LIKE 'db%'
             GROUP BY query HAVING query != '' ORDER BY avg_ms * count DESC LIMIT {lim:UInt32}",
            ['pid' => $project->id, 'from' => $r['from'], 'lim' => $limit]
        );
    }
}
