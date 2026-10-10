<?php

namespace Tests\Feature;

use App\Ingest\IngestQueue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestApiTest extends TestCase
{
    use RefreshDatabase;

    protected array $pushed = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pushed = [];
        $fake = new class($this->pushed) extends IngestQueue
        {
            public function __construct(public array &$store)
            {
                parent::__construct(null);
            }

            public function push(array $items): int
            {
                foreach ($items as $i) {
                    $this->store[] = $i;
                }

                return count($items);
            }
        };
        $this->app->instance(IngestQueue::class, $fake);
        config(['loggerxo.ingest.rate_limit_per_minute' => 0, 'cache.default' => 'array']);
    }

    protected function project(): Project
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'owner_id' => $user->id]);

        return Project::create(['organization_id' => $org->id, 'name' => 'Shop', 'platform' => 'laravel']);
    }

    public function test_rejects_unknown_key(): void
    {
        $this->postJson('/api/v1/envelope', ['events' => [['message' => 'x']]], ['X-Loggerxo-Key' => 'lx_'.str_repeat('a', 32)])
            ->assertStatus(401)->assertJsonPath('error', 'invalid_key');
    }

    public function test_accepts_envelope_and_queues_events(): void
    {
        $project = $this->project();
        $this->postJson('/api/v1/envelope', [
            'sdk' => ['name' => 'test', 'version' => '1'],
            'environment' => 'staging',
            'events' => [['type' => 'log', 'level' => 'info', 'message' => 'hello'], ['type' => 'error', 'exception' => ['type' => 'E', 'value' => 'v']]],
        ], ['X-Loggerxo-Key' => $project->public_key])
            ->assertStatus(202)->assertJson(['accepted' => 2, 'dropped' => 0]);

        $this->assertCount(2, $this->pushed);
        $this->assertSame($project->id, $this->pushed[0]['meta']['project_id']);
        $this->assertSame('staging', $this->pushed[0]['meta']['environment']);
        $this->assertSame('test/1', $this->pushed[0]['meta']['sdk']);
    }

    public function test_browser_endpoint_accepts_text_plain_and_checks_origin(): void
    {
        $project = $this->project();
        $project->update(['allowed_origins' => ['https://shop.example.com']]);

        $body = json_encode(['events' => [['type' => 'console', 'level' => 'warning', 'message' => 'deprecated']]]);

        $this->call('POST', '/api/v1/browser?key='.$project->public_key, [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ORIGIN' => 'https://evil.example.com'], $body)
            ->assertStatus(403);

        $this->call('POST', '/api/v1/browser?key='.$project->public_key, [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ORIGIN' => 'https://shop.example.com'], $body)
            ->assertStatus(202)->assertJson(['accepted' => 1]);
        $this->assertSame('javascript', $this->pushed[0]['meta']['platform']);
    }

    public function test_gzip_bodies_are_decoded(): void
    {
        $project = $this->project();
        $body = gzencode(json_encode(['events' => [['message' => 'zipped']]]));
        $this->call('POST', '/api/v1/envelope', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_CONTENT_ENCODING' => 'gzip', 'HTTP_X_LOGGERXO_KEY' => $project->public_key], $body)
            ->assertStatus(202)->assertJson(['accepted' => 1]);
    }

    public function test_disabled_project_is_rejected(): void
    {
        $project = $this->project();
        $project->update(['is_active' => false]);
        $this->postJson('/api/v1/envelope', ['events' => [['message' => 'x']]], ['X-Loggerxo-Key' => $project->public_key])->assertStatus(403);
    }
}
