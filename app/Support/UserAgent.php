<?php

namespace App\Support;

class UserAgent
{
    /**
     * Minimal user-agent parser: returns [browser, os, device].
     */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return ['', '', ''];
        }

        $browser = '';
        $os = '';
        $device = 'desktop';

        if (preg_match('/\b(bot|crawl|spider|slurp|facebookexternalhit|preview|curl|wget|python-requests|Go-http-client|GuzzleHttp|Symfony)\b/i', $ua)) {
            $browser = preg_match('/GuzzleHttp|Symfony|curl|wget|python|Go-http/i', $ua) ? 'HTTP client' : 'Bot';
            $device = 'bot';
        } elseif (preg_match('/Edg(?:e|A|iOS)?\/([\d.]+)/', $ua, $m)) {
            $browser = 'Edge '.self::major($m[1]);
        } elseif (preg_match('/OPR\/([\d.]+)|Opera\/([\d.]+)/', $ua, $m)) {
            $browser = 'Opera '.self::major($m[1] ?: ($m[2] ?? ''));
        } elseif (preg_match('/SamsungBrowser\/([\d.]+)/', $ua, $m)) {
            $browser = 'Samsung Internet '.self::major($m[1]);
        } elseif (preg_match('/YaBrowser\/([\d.]+)/', $ua, $m)) {
            $browser = 'Yandex '.self::major($m[1]);
        } elseif (preg_match('/(?:CriOS|Chrome)\/([\d.]+)/', $ua, $m)) {
            $browser = 'Chrome '.self::major($m[1]);
        } elseif (preg_match('/(?:FxiOS|Firefox)\/([\d.]+)/', $ua, $m)) {
            $browser = 'Firefox '.self::major($m[1]);
        } elseif (preg_match('/Version\/([\d.]+).*Safari/', $ua, $m)) {
            $browser = 'Safari '.self::major($m[1]);
        } elseif (str_contains($ua, 'Safari')) {
            $browser = 'Safari';
        } elseif (preg_match('/MSIE ([\d.]+)|Trident\/.*rv:([\d.]+)/', $ua, $m)) {
            $browser = 'IE '.self::major($m[1] ?: ($m[2] ?? ''));
        }

        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
            $os = 'Windows '.match ($m[1]) {
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7',
                default => $m[1],
            };
        } elseif (preg_match('/iPhone|iPad|iPod/', $ua)) {
            preg_match('/OS ([\d_]+)/', $ua, $m);
            $os = 'iOS'.(isset($m[1]) ? ' '.str_replace('_', '.', self::major($m[1], 2)) : '');
            $device = str_contains($ua, 'iPad') ? 'tablet' : 'mobile';
        } elseif (preg_match('/Android ([\d.]+)/', $ua, $m)) {
            $os = 'Android '.self::major($m[1]);
            $device = str_contains($ua, 'Mobile') ? 'mobile' : 'tablet';
        } elseif (preg_match('/Mac OS X ([\d_.]+)/', $ua, $m)) {
            $os = 'macOS '.str_replace('_', '.', self::major($m[1], 2));
        } elseif (str_contains($ua, 'CrOS')) {
            $os = 'ChromeOS';
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        }

        if ($device === 'desktop' && preg_match('/Mobile|Mobi/', $ua) && $os === '') {
            $device = 'mobile';
        }

        return [$browser, $os, $device];
    }

    protected static function major(string $version, int $parts = 1): string
    {
        $segments = preg_split('/[._]/', $version) ?: [];

        return implode('.', array_slice($segments, 0, $parts));
    }
}
