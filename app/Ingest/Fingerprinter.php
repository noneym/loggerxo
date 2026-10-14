<?php

namespace App\Ingest;

class Fingerprinter
{
    /**
     * Compute a stable grouping fingerprint (sha1) for a normalized event.
     */
    public static function compute(array $event): string
    {
        if (! empty($event['fingerprint']) && is_array($event['fingerprint'])) {
            $parts = array_map(fn ($p) => is_scalar($p) ? (string) $p : json_encode($p), $event['fingerprint']);
            $parts = array_filter($parts, fn ($p) => $p !== '' && $p !== '{{ default }}');
            // URL-like parts still carry ids and hashes from older SDKs: normalise them so one pattern is one issue.
            $parts = array_map(fn ($p) => str_contains($p, '/') ? self::normalizeUrl($p) : $p, $parts);
            if ($parts !== []) {
                return sha1('custom|'.$event['project_id'].'|'.implode('|', $parts));
            }
        }

        $type = $event['type'] ?? 'log';
        $exception = $event['exception'] ?? null;

        if ($exception && ! empty($exception['type'])) {
            $parts = ['exc', $exception['type'], self::normalizeMessage($exception['value'] ?? '')];
            $frames = self::significantFrames($exception['frames'] ?? []);
            foreach ($frames as $frame) {
                $parts[] = self::normalizeFile($frame['file'] ?? '').'#'.($frame['function'] ?? '');
            }
            if ($frames === [] && ! empty($event['transaction'])) {
                $parts[] = $event['transaction'];
            }

            return sha1($event['project_id'].'|'.implode('|', $parts));
        }

        $parts = [$type, $event['level'] ?? 'info', $event['logger'] ?? '', self::normalizeMessage($event['message'] ?? '')];

        return sha1($event['project_id'].'|'.implode('|', $parts));
    }

    /**
     * Replace volatile fragments (ids, hashes, timestamps, emails, quoted values) with placeholders.
     */
    public static function normalizeMessage(string $message): string
    {
        $message = mb_substr($message, 0, 1000);
        $patterns = [
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i' => '<uuid>',
            '/\b[0-9a-f]{24,}\b/i' => '<hash>',
            '/[\w.+-]+@[\w-]+\.[\w.-]+/' => '<email>',
            '/\b\d{1,3}(?:\.\d{1,3}){3}\b/' => '<ip>',
            '/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?/' => '<datetime>',
            '/\d{4}-\d{2}-\d{2}/' => '<date>',
            '/(https?:\/\/[^\s\'"]+?)\?[^\s\'"]*/' => '$1',
            '/"(?:[^"\\\\]|\\\\.){0,200}"/' => '"?"',
            "/'(?:[^'\\\\]|\\\\.){0,200}'/" => "'?'",
            '/0x[0-9a-f]+/i' => '<hex>',
            '/\b\d+(?:\.\d+)?\b/' => '<n>',
            '/\s+/' => ' ',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        return trim($message);
    }

    /**
     * Host plus path shape of a URL: query dropped, hex hashes and numbers replaced, file name generalised.
     */
    public static function normalizeUrl(string $url): string
    {
        $url = preg_replace('/[?#].*$/', '', $url) ?? $url;
        $url = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $url) ?? $url;
        $segments = explode('/', $url);
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ($i === 0) {
                continue; // host
            }
            if ($i === $last && preg_match('/\.([a-z0-9]{1,5})$/i', $segment, $m)) {
                $segments[$i] = ':file.'.strtolower($m[1]);
            } elseif (preg_match('/\d/', $segment)) {
                $segments[$i] = ':n'; // slugs and ids carry digits; words without digits are structure
            }
        }

        return implode('/', $segments);
    }

    public static function normalizeFile(string $file): string
    {
        // Strip query strings and content hashes from JS bundles: app.3f9a1c2b.js -> app.js
        $file = preg_replace('/\?.*$/', '', $file) ?? $file;
        $file = preg_replace('/[.-][0-9a-f]{6,}(?=\.[a-z]+$)/i', '', $file) ?? $file;
        // Strip origin from URLs so the same bundle on different hosts groups together.
        $file = preg_replace('#^https?://[^/]+#i', '', $file) ?? $file;
        // Strip absolute project paths that differ between servers: /var/www/releases/123/app -> app
        $file = preg_replace('#^.*?/(app|src|resources|routes|database|packages|vendor|public|lib)/#', '$1/', $file) ?? $file;

        return $file;
    }

    /**
     * Pick the frames that matter: in-app frames first, otherwise the innermost ones.
     */
    protected static function significantFrames(array $frames, int $limit = 5): array
    {
        $frames = array_values(array_filter($frames, fn ($f) => is_array($f)));
        if ($frames === []) {
            return [];
        }
        $inApp = array_values(array_filter($frames, fn ($f) => ! empty($f['in_app'])));
        $selected = $inApp !== [] ? $inApp : $frames;

        return array_slice($selected, 0, $limit);
    }
}
