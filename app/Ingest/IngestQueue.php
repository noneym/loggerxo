<?php

namespace App\Ingest;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Thin wrapper around the Redis list that buffers raw envelopes between the API and the worker.
 */
class IngestQueue
{
    public function __construct(protected ?Connection $redis = null) {}

    protected function redis(): Connection
    {
        return $this->redis ??= Redis::connection();
    }

    public function key(): string
    {
        return config('loggerxo.ingest.queue_key', 'lx:ingest');
    }

    public function deadKey(): string
    {
        return config('loggerxo.ingest.dead_key', 'lx:ingest:dead');
    }

    /**
     * @param  array<int, array>  $items  each item: ['meta' => [...], 'event' => [...]]
     */
    public function push(array $items): int
    {
        if ($items === []) {
            return 0;
        }
        $encoded = array_map(fn ($item) => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), $items);
        $this->redis()->rpush($this->key(), $encoded);

        return count($encoded);
    }

    public function requeue(array $rawItems): void
    {
        if ($rawItems !== []) {
            $this->redis()->rpush($this->key(), $rawItems);
        }
    }

    public function dead(array $rawItems): void
    {
        if ($rawItems !== []) {
            $this->redis()->rpush($this->deadKey(), $rawItems);
            $this->redis()->ltrim($this->deadKey(), -5000, -1);
        }
    }

    public function size(): int
    {
        return (int) $this->redis()->llen($this->key());
    }

    public function deadSize(): int
    {
        return (int) $this->redis()->llen($this->deadKey());
    }

    /**
     * Block up to $timeout seconds for the first item, then drain up to $max items.
     *
     * @return array<int, string> raw JSON strings
     */
    public function pop(int $max, int $timeout = 2, int $waitMs = 0): array
    {
        $first = $this->redis()->blpop([$this->key()], $timeout);
        if (! $first) {
            return [];
        }
        $items = [$first[1]];
        if ($max <= 1) {
            return $items;
        }
        if ($waitMs > 0 && $this->size() < $max) {
            usleep($waitMs * 1000);
        }
        $rest = $max - 1;
        $results = $this->redis()->transaction(function ($tx) use ($rest) {
            $tx->lrange($this->key(), 0, $rest - 1);
            $tx->ltrim($this->key(), $rest, -1);
        });
        $more = $results[0] ?? [];
        if (is_array($more)) {
            foreach ($more as $raw) {
                $items[] = $raw;
            }
        }

        return $items;
    }
}
