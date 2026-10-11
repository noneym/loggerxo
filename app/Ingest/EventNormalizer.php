<?php

namespace App\Ingest;

use App\Support\Levels;
use App\Support\UserAgent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Turns a raw SDK event (+ envelope meta) into a ClickHouse row.
 */
class EventNormalizer
{
    protected const SENSITIVE_KEYS = ['password', 'password_confirmation', 'passwd', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token', 'credit_card', 'card_number', 'cvv', 'ssn'];

    public function __construct(protected array $limits)
    {
        $this->limits += [
            'max_breadcrumbs' => 50,
            'max_frames' => 60,
            'max_message_length' => 8000,
        ];
    }

    public static function make(): static
    {
        return new static(config('loggerxo.ingest'));
    }

    /**
     * @param  array  $raw  the event as sent by the SDK
     * @param  array  $meta  envelope meta: project_id, environment, release, sdk, received_at, ip, platform_hint
     * @return array{table: string, row: array, event: array}|null
     */
    public function normalize(array $raw, array $meta): ?array
    {
        $type = strtolower((string) ($raw['type'] ?? 'log'));
        if (! in_array($type, ['error', 'log', 'console', 'transaction'], true)) {
            $type = isset($raw['exception']) ? 'error' : 'log';
        }

        $eventId = $this->uuid($raw['event_id'] ?? null);
        $timestamp = $this->timestamp($raw['timestamp'] ?? null, $meta['received_at'] ?? null);
        $environment = $this->str($raw['environment'] ?? $meta['environment'] ?? 'production', 64);
        $release = $this->str($raw['release'] ?? $meta['release'] ?? '', 128);
        $platform = $this->str($raw['platform'] ?? $meta['platform'] ?? 'php', 32);
        $userAgent = $this->str($raw['user_agent'] ?? data_get($raw, 'request.headers.user-agent') ?? data_get($raw, 'request.headers.User-Agent') ?? '', 512);
        [$browser, $os, $device] = UserAgent::parse($userAgent);
        $user = $this->user($raw['user'] ?? null, $meta['ip'] ?? null, $platform);
        $tags = $this->tags($raw['tags'] ?? []);
        $sdk = $this->str($meta['sdk'] ?? '', 64);
        $url = $this->str($raw['url'] ?? data_get($raw, 'request.url') ?? '', 2048);
        $serverName = $this->str($raw['server_name'] ?? '', 128);

        $base = [
            'event_id' => $eventId,
            'project_id' => (int) $meta['project_id'],
            'timestamp' => $timestamp->format('Y-m-d H:i:s.v'),
            'environment' => $environment,
            'release' => $release,
            'platform' => $platform,
            'server_name' => $serverName,
            'url' => $url,
            'user_id' => $user['id'] ?? '',
            'user_ip' => $user['ip'] ?? '',
            'user_agent' => $userAgent,
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'sdk' => $sdk,
            // ClickHouse Map columns need `{}` for empty values, PHP would encode [] otherwise.
            'tags' => (object) $tags,
        ];

        if ($type === 'transaction') {
            return $this->transaction($raw, $base, $user);
        }

        $level = Levels::normalize($raw['level'] ?? ($type === 'error' ? 'error' : 'info'));
        $exception = $this->exception($raw['exception'] ?? null);
        $message = $this->str($raw['message'] ?? '', $this->limits['max_message_length']);
        if ($message === '' && $exception) {
            $message = trim($exception['type'].': '.$exception['value']);
        }
        if ($message === '') {
            return null;
        }
        if ($type !== 'error' && $exception && $type === 'log' && Levels::atLeast($level, 'error')) {
            // Laravel reports exceptions through the log channel too; treat as error for grouping.
            $type = 'error';
        }

        $request = $this->request($raw['request'] ?? null);
        $breadcrumbs = $this->breadcrumbs($raw['breadcrumbs'] ?? []);
        $context = $this->context($raw['context'] ?? $raw['extra'] ?? null);

        $event = [
            'project_id' => $base['project_id'],
            'type' => $type,
            'level' => $level,
            'logger' => $this->str($raw['logger'] ?? '', 64),
            'message' => $message,
            'exception' => $exception,
            'fingerprint' => isset($raw['fingerprint']) && is_array($raw['fingerprint']) ? $raw['fingerprint'] : null,
            'transaction' => $this->str($raw['transaction'] ?? '', 255),
            'environment' => $environment,
            'platform' => $platform,
            'url' => $url,
        ];

        $fingerprint = Fingerprinter::compute($event);
        $culprit = $this->culprit($exception, $event['transaction'], $url, $type);
        $title = $exception ? $this->str($exception['type'].': '.$exception['value'], 500) : $this->str($message, 500);

        $payload = [
            'message' => $message,
            'exception' => $exception,
            'breadcrumbs' => $breadcrumbs,
            'request' => $request,
            'user' => $user,
            'context' => $context,
            'tags' => $tags,
            'sdk' => $sdk,
            'logger' => $event['logger'],
            'transaction' => $event['transaction'],
            'environment' => $environment,
            'release' => $release,
            'server_name' => $serverName,
            'url' => $url,
            'user_agent' => $userAgent,
            'culprit' => $culprit,
        ];

        $row = $base + [
            'issue_id' => 0,
            'type' => $type,
            'level' => $level,
            'logger' => $event['logger'],
            'message' => $message,
            'exception_type' => $exception['type'] ?? '',
            'exception_value' => $this->str($exception['value'] ?? '', 2000),
            'fingerprint' => $fingerprint,
            'transaction' => $event['transaction'],
            'http_method' => $this->str($request['method'] ?? '', 10),
            'status_code' => (int) ($raw['status_code'] ?? 0),
            'user_email' => $user['email'] ?? '',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        ];

        return [
            'table' => 'events',
            'row' => $row,
            'issue' => [
                'fingerprint' => $fingerprint,
                'type' => $type,
                'level' => $level,
                'platform' => $platform,
                'title' => $title,
                'culprit' => $culprit,
                'environment' => $environment,
                'timestamp' => $timestamp,
                'event_id' => $eventId,
                'metadata' => array_filter([
                    'exception_type' => $exception['type'] ?? null,
                    'logger' => $event['logger'] ?: null,
                    'transaction' => $event['transaction'] ?: null,
                    'release' => $release ?: null,
                ]),
            ],
        ];
    }

    protected function transaction(array $raw, array $base, array $user): ?array
    {
        $name = $this->str($raw['name'] ?? $raw['transaction'] ?? '', 255);
        if ($name === '') {
            return null;
        }
        $duration = (float) ($raw['duration_ms'] ?? 0);
        if ($duration < 0 || $duration > 86_400_000) {
            $duration = 0;
        }
        $measurements = [];
        foreach ((array) ($raw['measurements'] ?? []) as $k => $v) {
            if (is_numeric($v) && is_string($k) && count($measurements) < 30) {
                $measurements[$this->str($k, 40)] = (float) $v;
            }
        }
        $spans = [];
        foreach (array_slice((array) ($raw['spans'] ?? []), 0, 200) as $span) {
            if (! is_array($span)) {
                continue;
            }
            $spans[] = [
                'op' => $this->str($span['op'] ?? 'span', 64),
                'description' => $this->str($span['description'] ?? '', 2000),
                'start_ms' => round((float) ($span['start_ms'] ?? 0), 2),
                'duration_ms' => round((float) ($span['duration_ms'] ?? 0), 2),
                'data' => is_array($span['data'] ?? null) ? $this->truncateArray($span['data']) : null,
            ];
        }
        $payload = [
            'spans' => $spans,
            'measurements' => $measurements,
            'breadcrumbs' => $this->breadcrumbs($raw['breadcrumbs'] ?? []),
            'request' => $this->request($raw['request'] ?? null),
            'user' => $user,
            'context' => $this->context($raw['context'] ?? null),
            'tags' => (array) $base['tags'],
        ];

        $row = $base + [
            'op' => $this->str($raw['op'] ?? 'http.server', 32),
            'name' => $name,
            'http_method' => $this->str($raw['http_method'] ?? data_get($raw, 'request.method') ?? '', 10),
            'status_code' => (int) ($raw['status_code'] ?? 0),
            'duration_ms' => round($duration, 3),
            'db_time_ms' => round((float) ($measurements['db_time_ms'] ?? 0), 3),
            'db_query_count' => (int) ($measurements['db_query_count'] ?? 0),
            'memory_mb' => round((float) ($measurements['memory_mb'] ?? 0), 2),
            'measurements' => (object) $measurements,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        ];

        return ['table' => 'transactions', 'row' => $row, 'issue' => null];
    }

    /* ------------------------------------------------------------ pieces */

    protected function exception(mixed $exception): ?array
    {
        if (! is_array($exception)) {
            return null;
        }
        // Sentry-style {values: [...]} support
        if (isset($exception['values']) && is_array($exception['values'])) {
            $values = array_values($exception['values']);
            $exception = end($values) ?: [];
            $chain = array_slice($values, 0, -1);
            $exception['chain'] = array_reverse($chain);
        }
        $type = $this->str($exception['type'] ?? 'Exception', 255);
        $value = $this->str($exception['value'] ?? $exception['message'] ?? '', 4000);
        $frames = [];
        $rawFrames = $exception['frames'] ?? data_get($exception, 'stacktrace.frames', []);
        if (is_array($rawFrames)) {
            foreach (array_slice(array_values($rawFrames), 0, $this->limits['max_frames']) as $frame) {
                if (! is_array($frame)) {
                    continue;
                }
                $frames[] = array_filter([
                    'file' => $this->str($frame['file'] ?? $frame['filename'] ?? $frame['abs_path'] ?? '', 1024),
                    'line' => isset($frame['line']) ? (int) $frame['line'] : (isset($frame['lineno']) ? (int) $frame['lineno'] : null),
                    'col' => isset($frame['col']) ? (int) $frame['col'] : (isset($frame['colno']) ? (int) $frame['colno'] : null),
                    'function' => $this->str($frame['function'] ?? '', 255),
                    'class' => $this->str($frame['class'] ?? '', 255),
                    'in_app' => (bool) ($frame['in_app'] ?? false),
                    'context' => $this->frameContext($frame['context'] ?? null, $frame),
                ], fn ($v) => $v !== null && $v !== '' && $v !== []);
            }
        }
        $out = [
            'type' => $type,
            'value' => $value,
            'module' => $this->str($exception['module'] ?? $exception['file'] ?? '', 512),
            'code' => isset($exception['code']) && is_scalar($exception['code']) ? (string) $exception['code'] : null,
            'frames' => $frames,
        ];
        if (! empty($exception['chain']) && is_array($exception['chain'])) {
            $out['chain'] = [];
            foreach (array_slice($exception['chain'], 0, 5) as $prev) {
                if (is_array($prev)) {
                    $prevNorm = $this->exception(array_diff_key($prev, ['chain' => 1]));
                    if ($prevNorm) {
                        $out['chain'][] = $prevNorm;
                    }
                }
            }
        }

        return $out;
    }

    protected function frameContext(mixed $context, array $frame): ?array
    {
        if (is_array($context) && (isset($context['line']) || isset($context['pre']))) {
            return [
                'pre' => array_map(fn ($l) => $this->str($l, 300), array_slice((array) ($context['pre'] ?? []), -7)),
                'line' => $this->str($context['line'] ?? '', 300),
                'post' => array_map(fn ($l) => $this->str($l, 300), array_slice((array) ($context['post'] ?? []), 0, 7)),
            ];
        }
        if (isset($frame['context_line'])) {
            return [
                'pre' => array_map(fn ($l) => $this->str($l, 300), array_slice((array) ($frame['pre_context'] ?? []), -7)),
                'line' => $this->str($frame['context_line'], 300),
                'post' => array_map(fn ($l) => $this->str($l, 300), array_slice((array) ($frame['post_context'] ?? []), 0, 7)),
            ];
        }

        return null;
    }

    protected function request(mixed $request): ?array
    {
        if (! is_array($request)) {
            return null;
        }
        $headers = [];
        foreach ((array) ($request['headers'] ?? []) as $k => $v) {
            $key = strtolower((string) $k);
            if (in_array($key, self::SENSITIVE_KEYS, true)) {
                $v = '[Filtered]';
            }
            $headers[$this->str((string) $k, 80)] = $this->str(is_array($v) ? implode(', ', array_map('strval', $v)) : (string) $v, 1000);
            if (count($headers) >= 60) {
                break;
            }
        }

        return array_filter([
            'url' => $this->str($request['url'] ?? '', 2048),
            'method' => strtoupper($this->str($request['method'] ?? '', 10)),
            'headers' => $headers,
            'query' => $this->scrub((array) ($request['query'] ?? $request['query_string'] ?? [])),
            'data' => $this->scrub(is_array($request['data'] ?? null) ? $request['data'] : []),
            'cookies' => isset($request['cookies']) ? '[Filtered]' : null,
            'ip' => $this->str($request['ip'] ?? '', 45),
            'route' => $this->str($request['route'] ?? '', 255),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    protected function user(mixed $user, ?string $fallbackIp, string $platform): array
    {
        $out = [];
        if (is_array($user)) {
            foreach (['id', 'email', 'username', 'name', 'ip'] as $k) {
                if (isset($user[$k]) && is_scalar($user[$k])) {
                    $out[$k] = $this->str((string) $user[$k], 255);
                }
            }
            if (isset($user['ip_address'])) {
                $out['ip'] = $this->str((string) $user['ip_address'], 45);
            }
        }
        if (empty($out['ip']) && $fallbackIp && $platform === 'javascript') {
            $out['ip'] = $this->str($fallbackIp, 45);
        }

        return $out;
    }

    protected function tags(mixed $tags): array
    {
        $out = [];
        if (! is_array($tags)) {
            return $out;
        }
        foreach ($tags as $k => $v) {
            if (! is_string($k) || $k === '' || ! is_scalar($v)) {
                continue;
            }
            $out[$this->str($k, 64)] = $this->str(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, 200);
            if (count($out) >= 50) {
                break;
            }
        }

        return $out;
    }

    protected function breadcrumbs(mixed $crumbs): array
    {
        if (! is_array($crumbs)) {
            return [];
        }
        $out = [];
        foreach (array_slice(array_values($crumbs), -$this->limits['max_breadcrumbs']) as $c) {
            if (! is_array($c)) {
                continue;
            }
            $out[] = array_filter([
                'timestamp' => isset($c['timestamp']) ? $this->timestamp($c['timestamp'], null)->format('Y-m-d H:i:s.v') : null,
                'type' => $this->str($c['type'] ?? 'default', 32),
                'category' => $this->str($c['category'] ?? '', 64),
                'level' => Levels::normalize($c['level'] ?? 'info'),
                'message' => $this->str($c['message'] ?? '', 1000),
                'data' => is_array($c['data'] ?? null) ? $this->truncateArray($this->scrub($c['data'])) : null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    protected function context(mixed $context): array
    {
        if (! is_array($context)) {
            return [];
        }

        return $this->truncateArray($this->scrub($context));
    }

    protected function scrub(array $data, int $depth = 0): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = is_string($k) ? strtolower($k) : $k;
            if (is_string($key) && in_array($key, self::SENSITIVE_KEYS, true)) {
                $out[$k] = '[Filtered]';
            } elseif (is_array($v) && $depth < 6) {
                $out[$k] = $this->scrub($v, $depth + 1);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    protected function truncateArray(array $data, int $depth = 0, int $budget = 20000): array
    {
        $out = [];
        $count = 0;
        foreach ($data as $k => $v) {
            if ($count++ >= 100) {
                $out['…'] = '[truncated]';
                break;
            }
            if (is_string($v)) {
                $out[$k] = $this->str($v, 2000);
            } elseif (is_array($v)) {
                $out[$k] = $depth < 6 ? $this->truncateArray($v, $depth + 1) : '[array]';
            } elseif (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            } else {
                $out[$k] = '[object]';
            }
        }
        $encoded = json_encode($out);
        if ($encoded !== false && strlen($encoded) > $budget && $depth === 0) {
            return ['_truncated' => true, '_preview' => mb_substr($encoded, 0, $budget)];
        }

        return $out;
    }

    protected function culprit(?array $exception, string $transaction, string $url, string $type): string
    {
        if ($exception) {
            foreach ($exception['frames'] as $frame) {
                if (! empty($frame['in_app']) && ! empty($frame['file'])) {
                    return $this->str($this->shortFile($frame['file']).(isset($frame['line']) ? ':'.$frame['line'] : '').(! empty($frame['function']) ? ' in '.(! empty($frame['class']) ? $frame['class'].'::' : '').$frame['function'] : ''), 500);
                }
            }
            $first = $exception['frames'][0] ?? null;
            if ($first && ! empty($first['file'])) {
                return $this->str($this->shortFile($first['file']).(isset($first['line']) ? ':'.$first['line'] : ''), 500);
            }
            if (! empty($exception['module'])) {
                return $this->str($this->shortFile($exception['module']), 500);
            }
        }
        if ($transaction !== '') {
            return $transaction;
        }
        if ($url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            return $this->str($path ?: $url, 500);
        }

        return '';
    }

    protected function shortFile(string $file): string
    {
        $file = preg_replace('#^https?://[^/]+#i', '', $file) ?? $file;
        $file = preg_replace('#^.*?/(app|src|resources|routes|database|packages|vendor|public|lib|tests)/#', '$1/', $file) ?? $file;

        return $file;
    }

    protected function timestamp(mixed $value, mixed $fallback): CarbonImmutable
    {
        $now = CarbonImmutable::now('UTC');
        try {
            if (is_numeric($value)) {
                $value = (float) $value;
                if ($value > 1e12) {
                    $value /= 1000; // milliseconds
                }
                $ts = CarbonImmutable::createFromTimestampUTC((int) floor($value))->addMilliseconds((int) round(($value - floor($value)) * 1000));
            } elseif (is_string($value) && $value !== '') {
                $ts = CarbonImmutable::parse($value)->utc();
            } else {
                $ts = $fallback ? CarbonImmutable::parse($fallback)->utc() : $now;
            }
        } catch (\Throwable) {
            $ts = $now;
        }
        if ($ts->lt($now->subDays(30)) || $ts->gt($now->addMinutes(5))) {
            $ts = $now;
        }

        return $ts;
    }

    protected function uuid(mixed $value): string
    {
        if (is_string($value) && Str::isUuid($value)) {
            return strtolower($value);
        }
        if (is_string($value) && preg_match('/^[0-9a-f]{32}$/i', $value)) {
            return strtolower(substr($value, 0, 8).'-'.substr($value, 8, 4).'-'.substr($value, 12, 4).'-'.substr($value, 16, 4).'-'.substr($value, 20));
        }

        return (string) Str::uuid();
    }

    protected function str(mixed $value, int $max): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        $value = (string) $value;
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        // The ellipsis counts towards the limit so the result always fits a varchar($max) column.
        return mb_strlen($value) > $max ? mb_substr($value, 0, max(1, $max - 1)).'…' : $value;
    }
}
