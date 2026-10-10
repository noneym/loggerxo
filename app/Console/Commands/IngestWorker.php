<?php

namespace App\Console\Commands;

use App\Ingest\IngestProcessor;
use App\Ingest\IngestQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IngestWorker extends Command
{
    protected $signature = 'loggerxo:ingest
        {--once : Process a single batch and exit}
        {--max-time=0 : Exit after this many seconds (supervisor restarts the process)}
        {--batch= : Override batch size}';

    protected $description = 'Pull raw events from Redis and write them to ClickHouse in batches';

    protected bool $shouldStop = false;

    public function handle(IngestQueue $queue): int
    {
        $processor = IngestProcessor::make();
        $batchSize = (int) ($this->option('batch') ?: config('loggerxo.ingest.batch_size', 500));
        $waitMs = (int) config('loggerxo.ingest.batch_wait_ms', 1500);
        $maxTime = (int) $this->option('max-time');
        $started = time();
        $failures = 0;

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
        }

        $this->info(sprintf('Ingest worker started (batch=%d, wait=%dms)', $batchSize, $waitMs));

        while (! $this->shouldStop) {
            if ($maxTime > 0 && time() - $started >= $maxTime) {
                $this->info('Max time reached, exiting.');
                break;
            }

            try {
                $raw = $queue->pop($batchSize, 2, $waitMs);
            } catch (\Throwable $e) {
                Log::error('loggerxo: redis pop failed', ['error' => $e->getMessage()]);
                $this->error('Redis: '.$e->getMessage());
                sleep(3);

                continue;
            }

            if ($raw === []) {
                if ($this->option('once')) {
                    break;
                }

                continue;
            }

            $items = [];
            $undecodable = 0;
            foreach ($raw as $json) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $items[] = $decoded;
                } else {
                    $undecodable++;
                }
            }

            try {
                $stats = $processor->process($items);
                $failures = 0;
                $this->line(sprintf(
                    '[%s] events=%d transactions=%d issues=%d skipped=%d%s',
                    now()->format('H:i:s'),
                    $stats['events'],
                    $stats['transactions'],
                    $stats['issues'],
                    $stats['skipped'] + $undecodable,
                    $queue->size() > 0 ? ' backlog='.$queue->size() : ''
                ));
            } catch (\Throwable $e) {
                $failures++;
                Log::error('loggerxo: batch failed', ['error' => $e->getMessage(), 'count' => count($raw), 'failures' => $failures]);
                $this->error(sprintf('Batch failed (%d): %s', $failures, mb_substr($e->getMessage(), 0, 400)));
                $retry = [];
                $dead = [];
                foreach ($items as $item) {
                    $item['_attempts'] = ($item['_attempts'] ?? 0) + 1;
                    $encoded = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($item['_attempts'] >= 3) {
                        $dead[] = $encoded;
                    } else {
                        $retry[] = $encoded;
                    }
                }
                $queue->requeue($retry);
                $queue->dead($dead);
                sleep(min(30, 2 * $failures));
            }

            if ($this->option('once')) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
