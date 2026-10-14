<?php

namespace Tests\Unit;

use App\Ingest\Fingerprinter;
use PHPUnit\Framework\TestCase;

class FingerprinterTest extends TestCase
{
    public function test_messages_with_different_ids_group_together(): void
    {
        $a = $this->event(['exception' => ['type' => 'RuntimeException', 'value' => 'Order 4412 not found for user 9', 'frames' => [['file' => '/var/www/app/Http/OrderController.php', 'function' => 'show', 'in_app' => true]]]]);
        $b = $this->event(['exception' => ['type' => 'RuntimeException', 'value' => 'Order 9981 not found for user 12', 'frames' => [['file' => '/srv/releases/42/app/Http/OrderController.php', 'function' => 'show', 'in_app' => true]]]]);

        $this->assertSame(Fingerprinter::compute($a), Fingerprinter::compute($b));
    }

    public function test_different_exception_types_do_not_group(): void
    {
        $a = $this->event(['exception' => ['type' => 'RuntimeException', 'value' => 'boom', 'frames' => []]]);
        $b = $this->event(['exception' => ['type' => 'LogicException', 'value' => 'boom', 'frames' => []]]);

        $this->assertNotSame(Fingerprinter::compute($a), Fingerprinter::compute($b));
    }

    public function test_custom_fingerprint_wins(): void
    {
        $a = $this->event(['fingerprint' => ['checkout', 'payment-failed'], 'message' => 'x']);
        $b = $this->event(['fingerprint' => ['checkout', 'payment-failed'], 'message' => 'totally different']);

        $this->assertSame(Fingerprinter::compute($a), Fingerprinter::compute($b));
    }

    public function test_projects_are_isolated(): void
    {
        $a = $this->event(['message' => 'same']);
        $b = $this->event(['message' => 'same']);
        $b['project_id'] = 2;

        $this->assertNotSame(Fingerprinter::compute($a), Fingerprinter::compute($b));
    }

    public function test_message_normalization(): void
    {
        $n = Fingerprinter::normalizeMessage('User jane@example.com (id 42) failed at 2026-10-10 12:00:00 from 10.0.0.5 token "abc123" uuid 3fa85f64-5717-4562-b3fc-2c963f66afa6');

        $this->assertSame('User <email> (id <n>) failed at <datetime> from <ip> token "?" uuid <uuid>', $n);
    }

    public function test_js_bundle_hashes_are_stripped(): void
    {
        $this->assertSame('/assets/app.js', Fingerprinter::normalizeFile('https://shop.example.com/assets/app.9f8e7d6c.js?v=3'));
    }

    public function test_url_parts_in_custom_fingerprints_group_by_pattern(): void
    {
        $a = $this->event(['fingerprint' => ['resource', 'img', 'https://images2.cdn8.net/insecure/c:950:600/plain/s3://processed-videos/reading-in-satins-mp4_3037/thumbnail.jpg']]);
        $b = $this->event(['fingerprint' => ['resource', 'img', 'https://images2.cdn8.net/insecure/c:950:600/plain/s3://processed-videos/0404-12-mp4_5050/thumbnail.jpg?x=1']]);
        $c = $this->event(['fingerprint' => ['resource', 'script', 'https://mc.yandex.ru/metrika/tag.js']]);

        $this->assertSame(Fingerprinter::compute($a), Fingerprinter::compute($b));
        $this->assertNotSame(Fingerprinter::compute($a), Fingerprinter::compute($c));
        $this->assertSame('cdn.example.com/:n/items/:n/:file.png', Fingerprinter::normalizeUrl('https://cdn.example.com/v2/items/3fa85f64571745/cover-12.png?size=l'));
    }

    protected function event(array $overrides): array
    {
        return $overrides + ['project_id' => 1, 'type' => 'error', 'level' => 'error', 'logger' => '', 'message' => '', 'transaction' => ''];
    }
}
