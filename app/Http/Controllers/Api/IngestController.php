<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ingest\IngestQueue;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class IngestController extends Controller
{
    public function __construct(protected IngestQueue $queue) {}

    /**
     * Server SDKs (Laravel / PHP / generic HTTP). Key in X-Loggerxo-Key, Authorization: Bearer, or ?key=.
     */
    public function envelope(Request $request): JsonResponse
    {
        $key = $request->header('X-Loggerxo-Key') ?: $request->bearerToken() ?: $request->query('key');
        $project = $key ? Project::findByKeyCached((string) $key) : null;
        if (! $project) {
            return $this->error(401, 'invalid_key', 'Unknown or missing project key');
        }

        return $this->accept($request, $project, (int) config('loggerxo.ingest.max_events_per_request', 500), 'server');
    }

    /**
     * Browser SDK. Key in ?key= so sendBeacon / keepalive requests need no custom headers (no preflight).
     */
    public function browser(Request $request): JsonResponse
    {
        $key = $request->query('key') ?: $request->header('X-Loggerxo-Key');
        $project = $key ? Project::findByKeyCached((string) $key) : null;
        if (! $project) {
            return $this->error(401, 'invalid_key', 'Unknown or missing project key');
        }
        $origin = $request->header('Origin') ?: ($request->header('Referer') ? $this->originOf($request->header('Referer')) : null);
        if (! $project->originAllowed($origin)) {
            return $this->error(403, 'origin_not_allowed', 'This origin is not allowed for the project');
        }

        return $this->accept($request, $project, (int) config('loggerxo.ingest.max_browser_events_per_request', 50), 'browser');
    }

    protected function accept(Request $request, Project $project, int $maxEvents, string $source): JsonResponse
    {
        if (! $project->is_active) {
            return $this->error(403, 'project_disabled', 'Project is disabled');
        }

        $body = $request->getContent();
        if (strtolower((string) $request->header('Content-Encoding')) === 'gzip' && $body !== '') {
            $decoded = @gzdecode($body);
            if ($decoded === false) {
                return $this->error(400, 'bad_gzip', 'Could not decode gzip body');
            }
            $body = $decoded;
        }
        $maxBytes = (int) config('loggerxo.ingest.max_body_kb', 2048) * 1024;
        if (strlen($body) > $maxBytes) {
            return $this->error(413, 'payload_too_large', 'Payload exceeds '.config('loggerxo.ingest.max_body_kb').' KB');
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return $this->error(400, 'invalid_json', 'Body must be a JSON object');
        }

        // Allow a single bare event as well as {events: [...]}
        $events = $payload['events'] ?? null;
        if ($events === null && (isset($payload['type']) || isset($payload['message']) || isset($payload['exception']))) {
            $events = [$payload];
        }
        if (! is_array($events) || $events === []) {
            return $this->error(400, 'no_events', 'No events in payload');
        }

        $limit = $this->rateLimit($project);
        if ($limit !== null && $limit['remaining'] <= 0) {
            return $this->error(429, 'rate_limited', 'Project rate limit exceeded', ['Retry-After' => (string) $limit['retry_after']]);
        }

        $sdk = is_array($payload['sdk'] ?? null) ? trim(($payload['sdk']['name'] ?? 'sdk').'/'.($payload['sdk']['version'] ?? '')) : (string) ($payload['sdk'] ?? $source);
        $meta = [
            'project_id' => $project->id,
            'environment' => is_string($payload['environment'] ?? null) ? mb_substr($payload['environment'], 0, 64) : 'production',
            'release' => is_string($payload['release'] ?? null) ? mb_substr($payload['release'], 0, 128) : '',
            'platform' => $source === 'browser' ? 'javascript' : (is_string($payload['platform'] ?? null) ? mb_substr($payload['platform'], 0, 32) : 'php'),
            'sdk' => mb_substr($sdk, 0, 64),
            'received_at' => now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            'ip' => $request->ip(),
        ];

        $items = [];
        $dropped = 0;
        $maxEventBytes = (int) config('loggerxo.ingest.max_event_bytes', 262144);
        $allowed = $limit ? min($maxEvents, $limit['remaining']) : $maxEvents;
        foreach (array_values($events) as $i => $event) {
            if (! is_array($event)) {
                $dropped++;

                continue;
            }
            if (count($items) >= $allowed) {
                $dropped++;

                continue;
            }
            if (strlen(json_encode($event) ?: '') > $maxEventBytes) {
                $dropped++;

                continue;
            }
            $items[] = ['meta' => $meta, 'event' => $event];
        }

        $accepted = $this->queue->push($items);
        if ($limit !== null && $accepted > 0) {
            $this->consumeRateLimit($project, $accepted);
        }

        return response()->json(['accepted' => $accepted, 'dropped' => $dropped], 202);
    }

    /**
     * @return array{remaining: int, retry_after: int}|null
     */
    protected function rateLimit(Project $project): ?array
    {
        $perMinute = (int) ($project->setting('rate_limit_per_minute') ?? config('loggerxo.ingest.rate_limit_per_minute', 6000));
        if ($perMinute <= 0) {
            return null;
        }
        $key = sprintf('lx:rl:%d:%d', $project->id, intdiv(time(), 60));
        try {
            $used = (int) Redis::connection()->get($key);
        } catch (\Throwable) {
            return null;
        }

        return ['remaining' => max(0, $perMinute - $used), 'retry_after' => 60 - (time() % 60)];
    }

    protected function consumeRateLimit(Project $project, int $count): void
    {
        $key = sprintf('lx:rl:%d:%d', $project->id, intdiv(time(), 60));
        try {
            $redis = Redis::connection();
            $redis->incrby($key, $count);
            $redis->expire($key, 120);
        } catch (\Throwable) {
            // best effort
        }
    }

    protected function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (! $parts || empty($parts['host'])) {
            return null;
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    protected function error(int $status, string $code, string $message, array $headers = []): JsonResponse
    {
        return response()->json(['error' => $code, 'message' => $message], $status, $headers);
    }
}
