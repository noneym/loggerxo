<?php

namespace App\Support;

class Levels
{
    public const ORDER = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3, 'error' => 4, 'critical' => 5, 'alert' => 6, 'emergency' => 7];

    public static function normalize(?string $level): string
    {
        $level = strtolower(trim((string) $level));

        return match ($level) {
            '' => 'info',
            'warn' => 'warning',
            'err', 'fatal', 'severe' => 'error',
            'log', 'verbose', 'trace' => 'info',
            'crit' => 'critical',
            'emerg', 'panic' => 'emergency',
            default => array_key_exists($level, self::ORDER) ? $level : 'info',
        };
    }

    public static function rank(string $level): int
    {
        return self::ORDER[self::normalize($level)] ?? 1;
    }

    public static function atLeast(string $level, string $min): bool
    {
        return self::rank($level) >= self::rank($min);
    }

    public static function all(): array
    {
        return array_keys(self::ORDER);
    }

    public static function color(string $level): string
    {
        return match (self::normalize($level)) {
            'emergency', 'alert', 'critical' => 'rose',
            'error' => 'red',
            'warning' => 'amber',
            'notice' => 'sky',
            'info' => 'blue',
            default => 'zinc',
        };
    }
}
