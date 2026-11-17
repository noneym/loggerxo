<?php

namespace App\Mcp;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Services\EventRepository;
use App\Support\Levels;
use Illuminate\Support\Str;

/**
 * The read-only tools exposed over MCP. Every tool acts as the token's user and only sees their projects.
 */
class Tools
{
    protected const EVENT_TYPES = ['error', 'log', 'console'];

    protected const STATUSES = [Issue::STATUS_OPEN, Issue::STATUS_RESOLVED, Issue::STATUS_IGNORED, 'all'];

    public function __construct(protected EventRepository $events) {}

    /**
     * Tool descriptors for `tools/list`.
     */
    public function definitions(): array
    {
        $project = ['type' => 'integer', 'description' => 'Project id from list_projects.'];
        $range = ['type' => 'string', 'enum' => array_keys(EventRepository::RANGES), 'description' => 'Time window ending now. Default 24h.'];
        $cursor = ['type' => 'string', 'description' => 'next_cursor from the previous page, to get older events.'];

        $tools = [
            'list_projects' => [
                'List the projects this account can read, with their open issue counts. Start here to get a project_id.',
                [],
            ],
            'get_project_stats' => [
                'Event, issue and performance totals for one project over a time window, plus issue counts by status.',
                ['project_id' => $project, 'range' => $range],
                ['project_id'],
            ],
            'list_issues' => [
                'List the issues (grouped errors and repeated log problems) of a project, most recent first. Use get_issue for the stack trace.',
                [
                    'project_id' => $project,
                    'status' => ['type' => 'string', 'enum' => self::STATUSES, 'description' => 'Default open.'],
                    'type' => ['type' => 'string', 'enum' => self::EVENT_TYPES],
                    'level' => ['type' => 'string', 'enum' => array_keys(Levels::ORDER)],
                    'environment' => ['type' => 'string', 'description' => 'Only issues seen in this environment, e.g. production.'],
                    'query' => ['type' => 'string', 'description' => 'Text in the title or culprit, or the start of the short id.'],
                    'sort' => ['type' => 'string', 'enum' => ['last_seen', 'first_seen', 'events'], 'description' => 'Default last_seen.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Default 20.'],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                ],
                ['project_id'],
            ],
            'get_issue' => [
                'Everything needed to diagnose one issue: its state, the latest event (exception, stack trace with source context for application frames, request, user, breadcrumbs, context, tags) and recent comments. Pass event_id to look at a specific occurrence instead of the latest.',
                [
                    'issue_id' => ['type' => 'integer', 'description' => 'Issue id from list_issues.'],
                    'event_id' => ['type' => 'string', 'description' => 'Event UUID from list_issue_events.'],
                ],
                ['issue_id'],
            ],
            'list_issue_events' => [
                'The individual occurrences of an issue over the last 30 days, newest first: when, where, which release, user and URL. Use it to see how an issue is spread, then get_event for one of them.',
                [
                    'issue_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Default 20.'],
                    'cursor' => $cursor,
                ],
                ['issue_id'],
            ],
            'get_event' => [
                'One event in full by its UUID: exception and stack trace, request, user, breadcrumbs, context and tags.',
                ['project_id' => $project, 'event_id' => ['type' => 'string', 'description' => 'Event UUID.']],
                ['project_id', 'event_id'],
            ],
            'search_events' => [
                'Search raw errors, server logs and browser console output of a project, newest first. query matches the message, exception value, URL or transaction; prefixes narrow it: url:/checkout, user:jane@example.com, tag:key=value, exception:TypeError, transaction:, server:, release:, logger:.',
                [
                    'project_id' => $project,
                    'query' => ['type' => 'string'],
                    'types' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::EVENT_TYPES], 'description' => 'Default all three.'],
                    'levels' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(Levels::ORDER)]],
                    'environment' => ['type' => 'string'],
                    'release' => ['type' => 'string'],
                    'range' => $range,
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Default 30.'],
                    'cursor' => $cursor,
                ],
                ['project_id'],
            ],
        ];

        $out = [];
        foreach ($tools as $name => $tool) {
            $out[] = [
                'name' => $name,
                'description' => $tool[0],
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $tool[1] === [] ? new \stdClass : $tool[1],
                    'required' => $tool[2] ?? [],
                ],
                'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
            ];
        }

        return $out;
    }

    public function has(string $name): bool
    {
        return method_exists($this, $this->method($name));
    }

    /**
     * @throws ToolError when the arguments are unusable or the target does not exist for this user
     */
    public function call(User $user, string $name, array $args): array
    {
        return $this->{$this->method($name)}($user, $args);
    }

    protected function method(string $name): string
    {
        return 'tool'.Str::studly($name);
    }

    /* -------------------------------------------------------------- tools */

    protected function toolListProjects(User $user, array $args): array
    {
        $projects = $user->accessibleProjects()
            ->with('organization')
            ->withCount(['issues as open_issues' => fn ($q) => $q->where('status', Issue::STATUS_OPEN)])
            ->orderBy('name')
            ->get();

        return [
            'projects' => $projects->map(fn (Project $p) => [
                'project_id' => $p->id,
                'name' => $p->name,
                'organization' => $p->organization?->name,
                'platform' => $p->platform,
                'is_active' => $p->is_active,
                'open_issues' => $p->open_issues,
                'last_event_at' => $p->last_event_at?->toIso8601String(),
                'url' => route('projects.show', $p->id),
            ])->all(),
        ];
    }

    protected function toolGetProjectStats(User $user, array $args): array
    {
        $project = $this->project($user, $args);
        $range = $this->range($args);
        $byStatus = Issue::where('project_id', $project->id)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'project' => ['project_id' => $project->id, 'name' => $project->name, 'platform' => $project->platform],
            'range' => $range,
            'issues_by_status' => [
                'open' => (int) ($byStatus['open'] ?? 0),
                'resolved' => (int) ($byStatus['resolved'] ?? 0),
                'ignored' => (int) ($byStatus['ignored'] ?? 0),
            ],
            'totals' => $this->events->projectSummary($project, $range),
            'totals_help' => 'errors/logs/console are event counts, issues is distinct issues with events, users is distinct affected users; transactions, p50, p95 (ms) and failed (HTTP 5xx) describe performance.',
        ];
    }

    protected function toolListIssues(User $user, array $args): array
    {
        $project = $this->project($user, $args);
        $status = $this->enum($args, 'status', self::STATUSES, Issue::STATUS_OPEN);
        $type = $this->enum($args, 'type', self::EVENT_TYPES);
        $level = $this->enum($args, 'level', array_keys(Levels::ORDER));
        $sort = $this->enum($args, 'sort', ['last_seen', 'first_seen', 'events'], 'last_seen');
        $limit = $this->int($args, 'limit', 20, 1, 50);
        $page = $this->int($args, 'page', 1, 1, 100000);
        $environment = $this->string($args, 'environment');
        $search = $this->string($args, 'query');

        $query = Issue::where('project_id', $project->id)->with('assignee');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($type !== null) {
            $query->where('type', $type);
        }
        if ($level !== null) {
            $query->where('level', $level);
        }
        if ($environment !== '') {
            $query->whereJsonContains('environments', $environment);
        }
        if ($search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('culprit', 'like', $term)->orWhere('fingerprint', 'like', strtolower($search).'%'));
        }
        $query->orderByDesc(match ($sort) {
            'first_seen' => 'first_seen',
            'events' => 'events_count',
            default => 'last_seen',
        })->orderByDesc('id');

        $total = (clone $query)->count();
        $issues = $query->forPage($page, $limit)->get();

        return [
            'total' => $total,
            'page' => $page,
            'has_more' => $page * $limit < $total,
            'issues' => $issues->map(fn (Issue $i) => $this->formatIssue($i))->all(),
        ];
    }

    protected function toolGetIssue(User $user, array $args): array
    {
        [$issue, $project] = $this->issue($user, $args);
        $eventId = $this->uuid($args, 'event_id');

        $event = $eventId ? $this->events->getEvent($project, $eventId) : null;
        if ($eventId && (! $event || (int) ($event['issue_id'] ?? 0) !== $issue->id)) {
            throw new ToolError("Event {$eventId} does not belong to issue {$issue->id}. Use list_issue_events to find its events.");
        }
        $event ??= $this->events->latestIssueEvent($project, $issue->id);

        $comments = $issue->comments()->with('user')->limit(15)->get();

        return [
            'issue' => $this->formatIssue($issue) + [
                'project_id' => $project->id,
                'project' => $project->name,
                'resolved_at' => $issue->resolved_at?->toIso8601String(),
                'ignored_until' => $issue->ignored_until?->toIso8601String(),
                'metadata' => $issue->metadata ?: null,
            ],
            'event' => $event ? $this->formatEvent($event) : null,
            'event_note' => $event ? null : 'No stored event for this issue; its events are past the retention window.',
            'activity' => $comments->map(fn ($c) => [
                'at' => $c->created_at?->toIso8601String(),
                'by' => $c->user?->name,
                'kind' => $c->kind,
                'body' => $c->body,
            ])->all(),
        ];
    }

    protected function toolListIssueEvents(User $user, array $args): array
    {
        [$issue, $project] = $this->issue($user, $args);
        $limit = $this->int($args, 'limit', 20, 1, 50);
        $result = $this->events->listEvents($project, ['issue_id' => $issue->id, 'before' => $this->cursor($args)], '30d', $limit);

        return $this->eventPage($result) + ['issue_id' => $issue->id, 'events_count' => (int) $issue->events_count];
    }

    protected function toolGetEvent(User $user, array $args): array
    {
        $project = $this->project($user, $args);
        $eventId = $this->uuid($args, 'event_id') ?? throw new ToolError('event_id is required.');
        $event = $this->events->getEvent($project, $eventId);
        if (! $event) {
            throw new ToolError("No event {$eventId} in project {$project->id}. It may be past the retention window.");
        }

        return ['event' => $this->formatEvent($event)];
    }

    protected function toolSearchEvents(User $user, array $args): array
    {
        $project = $this->project($user, $args);
        $range = $this->range($args);
        $filters = [
            'types' => $this->enumList($args, 'types', self::EVENT_TYPES) ?: self::EVENT_TYPES,
            'levels' => $this->enumList($args, 'levels', array_keys(Levels::ORDER)),
            'environment' => $this->string($args, 'environment'),
            'release' => $this->string($args, 'release'),
            'search' => $this->string($args, 'query'),
            'before' => $this->cursor($args),
        ];
        $result = $this->events->listEvents($project, $filters, $range, $this->int($args, 'limit', 30, 1, 100));

        return $this->eventPage($result) + ['range' => $range];
    }

    /* --------------------------------------------------------- formatting */

    protected function formatIssue(Issue $issue): array
    {
        return [
            'issue_id' => $issue->id,
            'short_id' => $issue->shortId(),
            'title' => $issue->title,
            'culprit' => $issue->culprit,
            'type' => $issue->type,
            'level' => $issue->level,
            'platform' => $issue->platform,
            'status' => $issue->status,
            'is_regressed' => $issue->is_regressed,
            'events_count' => (int) $issue->events_count,
            'first_seen' => $issue->first_seen?->toIso8601String(),
            'last_seen' => $issue->last_seen?->toIso8601String(),
            'environments' => $issue->environments ?: [],
            'assignee' => $issue->assignee?->name,
            'url' => $issue->url(),
        ];
    }

    protected function eventPage(array $result): array
    {
        return [
            'events' => array_map(fn (array $row) => $this->formatEventRow($row), $result['rows']),
            'has_more' => $result['has_more'],
            'next_cursor' => $result['has_more'] ? $result['next'] : null,
        ];
    }

    /**
     * A list row: the indexed columns only, without empty values.
     */
    protected function formatEventRow(array $row): array
    {
        if (isset($row['message'])) {
            $row['message'] = Str::limit((string) $row['message'], 600);
        }
        if (isset($row['exception_value'])) {
            $row['exception_value'] = Str::limit((string) $row['exception_value'], 600);
        }
        if (isset($row['issue_id'])) {
            $row['issue_id'] = (int) $row['issue_id'];
        }

        return $this->compact($row);
    }

    /**
     * A decoded event (see EventRepository::decode) reduced to what helps a diagnosis.
     */
    protected function formatEvent(array $event): array
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $exception = is_array($payload['exception'] ?? null) ? $this->formatException($payload['exception']) : null;

        return $this->compact([
            'event_id' => $event['event_id'] ?? null,
            'issue_id' => (int) ($event['issue_id'] ?? 0),
            'type' => $event['type'] ?? null,
            'level' => $event['level'] ?? null,
            'timestamp' => $event['timestamp'] ?? null,
            'environment' => $event['environment'] ?? null,
            'release' => $event['release'] ?? null,
            'platform' => $event['platform'] ?? null,
            'server_name' => $event['server_name'] ?? null,
            'logger' => $event['logger'] ?? null,
            'transaction' => $event['transaction'] ?? null,
            'url' => $event['url'] ?? null,
            'http_method' => $event['http_method'] ?? null,
            'status_code' => (int) ($event['status_code'] ?? 0),
            'browser' => $event['browser'] ?? null,
            'os' => $event['os'] ?? null,
            'device' => $event['device'] ?? null,
            'sdk' => $event['sdk'] ?? null,
            // The exception already carries the message; repeat it only for plain log lines.
            'message' => $exception ? null : ($event['message'] ?? null),
            'exception' => $exception,
            'user' => $payload['user'] ?? null,
            'tags' => $event['tags'] ?? null,
            'request' => $this->clip($payload['request'] ?? null, 8000),
            'breadcrumbs' => $this->clip(array_slice((array) ($payload['breadcrumbs'] ?? []), -30), 12000),
            'context' => $this->clip($payload['context'] ?? null, 12000),
        ]);
    }

    /**
     * Frames are listed innermost first. Source context is kept for application frames only
     * (or the top three when none is marked in_app) so vendor frames do not drown the answer.
     */
    protected function formatException(array $exception, int $depth = 0): array
    {
        $frames = array_values(array_filter((array) ($exception['frames'] ?? []), 'is_array'));
        $hasInApp = (bool) array_filter($frames, fn ($f) => ! empty($f['in_app']));
        foreach ($frames as $i => $frame) {
            $keep = $hasInApp ? ! empty($frame['in_app']) : $i < 3;
            if (! $keep) {
                unset($frames[$i]['context']);
            }
        }

        $out = $this->compact([
            'type' => $exception['type'] ?? null,
            'value' => $exception['value'] ?? null,
            'module' => $exception['module'] ?? null,
            'code' => $exception['code'] ?? null,
            'frames' => $frames,
        ]);
        if ($depth === 0 && ! empty($exception['chain']) && is_array($exception['chain'])) {
            $out['caused_by'] = array_map(fn ($prev) => $this->formatException((array) $prev, 1), array_values($exception['chain']));
        }

        return $out;
    }

    /**
     * Replace an oversized structure with a text preview so one event cannot flood the model's context.
     */
    protected function clip(mixed $value, int $bytes): mixed
    {
        if ($value === null || $value === []) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false || strlen($json) <= $bytes) {
            return $value;
        }

        return ['_truncated' => true, '_preview' => mb_strcut($json, 0, $bytes)];
    }

    protected function compact(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== 0);
    }

    /* ---------------------------------------------------------- arguments */

    protected function project(User $user, array $args): Project
    {
        $id = $this->int($args, 'project_id', null, 1, PHP_INT_MAX) ?? throw new ToolError('project_id is required. Call list_projects to find it.');
        $project = $user->accessibleProjects()->whereKey($id)->first();

        return $project ?? throw new ToolError("No project {$id} for this account. Call list_projects to see the available ids.");
    }

    /**
     * @return array{0: Issue, 1: Project}
     */
    protected function issue(User $user, array $args): array
    {
        $id = $this->int($args, 'issue_id', null, 1, PHP_INT_MAX) ?? throw new ToolError('issue_id is required. Call list_issues to find it.');
        $issue = Issue::with('assignee')->find($id);
        $project = $issue ? $user->accessibleProjects()->whereKey($issue->project_id)->first() : null;
        if (! $project) {
            // Same answer for a missing issue and one in somebody else's project.
            throw new ToolError("No issue {$id} for this account. Call list_issues to see the available ids.");
        }

        return [$issue, $project];
    }

    protected function range(array $args): string
    {
        return $this->enum($args, 'range', array_keys(EventRepository::RANGES), '24h');
    }

    protected function string(array $args, string $key): string
    {
        $value = $args[$key] ?? '';
        if (! is_scalar($value)) {
            throw new ToolError("{$key} must be a string.");
        }

        return trim(mb_substr((string) $value, 0, 500));
    }

    protected function int(array $args, string $key, ?int $default, int $min, int $max): ?int
    {
        $value = $args[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new ToolError("{$key} must be an integer.");
        }

        return max($min, min($max, (int) $value));
    }

    protected function enum(array $args, string $key, array $allowed, ?string $default = null): ?string
    {
        $value = $args[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new ToolError("{$key} must be one of: ".implode(', ', $allowed).'.');
        }

        return $value;
    }

    protected function enumList(array $args, string $key, array $allowed): array
    {
        $values = $args[$key] ?? [];
        if (is_string($values)) {
            $values = array_filter(array_map('trim', explode(',', $values)), fn ($v) => $v !== '');
        }
        if (! is_array($values) || array_diff($values, $allowed) !== []) {
            throw new ToolError("{$key} must be a list of: ".implode(', ', $allowed).'.');
        }

        return array_values(array_unique($values));
    }

    protected function uuid(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! Str::isUuid($value)) {
            throw new ToolError("{$key} must be an event UUID.");
        }

        return strtolower($value);
    }

    protected function cursor(array $args): ?string
    {
        $value = $args['cursor'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d+)?\|[0-9a-f-]{36}$/i', $value)) {
            throw new ToolError('cursor must be the next_cursor value of a previous page.');
        }

        return $value;
    }
}
