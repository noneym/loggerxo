<?php

namespace Tests\Unit;

use App\Ingest\EventNormalizer;
use PHPUnit\Framework\TestCase;

class EventNormalizerTest extends TestCase
{
    protected function normalizer(): EventNormalizer
    {
        return new EventNormalizer(['max_breadcrumbs' => 50, 'max_frames' => 60, 'max_message_length' => 8000]);
    }

    protected function meta(): array
    {
        return ['project_id' => 7, 'environment' => 'production', 'release' => '1.0', 'platform' => 'php', 'sdk' => 'test/1', 'received_at' => '2026-10-10T12:00:00Z', 'ip' => '9.9.9.9'];
    }

    public function test_error_event_builds_row_and_issue(): void
    {
        $out = $this->normalizer()->normalize([
            'type' => 'error',
            'timestamp' => '2026-10-10T11:59:00.250Z',
            'exception' => ['type' => 'RuntimeException', 'value' => 'Order 1 missing', 'frames' => [['file' => '/var/www/app/Http/X.php', 'line' => 3, 'function' => 'show', 'in_app' => true]]],
            'request' => ['url' => 'https://x.test/orders/1', 'method' => 'get', 'headers' => ['Authorization' => 'Bearer secret', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/129.0.0.0 Safari/537.36']],
            'user' => ['id' => 5, 'email' => 'a@b.c'],
            'tags' => ['module' => 'orders', 'flag' => true],
        ], $this->meta());

        $this->assertSame('events', $out['table']);
        $row = $out['row'];
        $this->assertSame(7, $row['project_id']);
        $this->assertSame('error', $row['type']);
        $this->assertSame('error', $row['level']);
        $this->assertSame('2026-10-10 11:59:00.250', $row['timestamp']);
        $this->assertSame('RuntimeException', $row['exception_type']);
        $this->assertSame('GET', $row['http_method']);
        $this->assertSame('a@b.c', $row['user_email']);
        $this->assertStringStartsWith('Chrome', $row['browser']);
        $this->assertSame('true', ((array) $row['tags'])['flag']);
        $payload = json_decode($row['payload'], true);
        $this->assertSame('[Filtered]', $payload['request']['headers']['Authorization']);
        $this->assertSame('RuntimeException: Order 1 missing', $out['issue']['title']);
        $this->assertSame('app/Http/X.php:3 in show', $out['issue']['culprit']);
    }

    public function test_log_without_message_is_dropped(): void
    {
        $this->assertNull($this->normalizer()->normalize(['type' => 'log', 'level' => 'info'], $this->meta()));
    }

    public function test_level_aliases_are_normalized(): void
    {
        $out = $this->normalizer()->normalize(['type' => 'log', 'level' => 'WARN', 'message' => 'hi'], $this->meta());
        $this->assertSame('warning', $out['row']['level']);
    }

    public function test_future_timestamps_are_clamped(): void
    {
        $out = $this->normalizer()->normalize(['type' => 'log', 'message' => 'hi', 'timestamp' => '2999-01-01T00:00:00Z'], $this->meta());
        $this->assertLessThanOrEqual(time() + 5, strtotime($out['row']['timestamp'].' UTC'));
    }

    public function test_transaction_row(): void
    {
        $out = $this->normalizer()->normalize([
            'type' => 'transaction', 'name' => 'GET /x', 'duration_ms' => 12.5, 'status_code' => 200,
            'measurements' => ['db_time_ms' => 3, 'db_query_count' => 2],
            'spans' => [['op' => 'db.query', 'description' => 'select 1', 'start_ms' => 1, 'duration_ms' => 0.5]],
        ], $this->meta());

        $this->assertSame('transactions', $out['table']);
        $this->assertSame(12.5, $out['row']['duration_ms']);
        $this->assertSame(2, $out['row']['db_query_count']);
        $this->assertNull($out['issue']);
        $this->assertCount(1, json_decode($out['row']['payload'], true)['spans']);
    }

    public function test_empty_tags_encode_as_json_object(): void
    {
        $out = $this->normalizer()->normalize(['type' => 'log', 'message' => 'hi'], $this->meta());
        $this->assertSame('{}', json_encode($out['row']['tags']));
    }
}
