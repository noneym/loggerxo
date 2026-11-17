<?php

namespace Tests\Feature;

use App\Livewire\Account\Settings;
use App\Models\ApiToken;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\ClickHouse\ClickHouseClient;
use App\Services\ClickHouse\ClickHouseException;
use App\Services\EventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class McpTest extends TestCase
{
    use RefreshDatabase;

    public const EVENT_ID = '0b9d6a3e-6c1f-4d55-9a57-0d7f5a0e1c11';

    protected User $user;

    protected Project $project;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = $this->projectFor($this->user, 'Shop');
        [, $this->token] = ApiToken::issue($this->user, 'test');
        $this->fakeEvents();
    }

    protected function projectFor(User $user, string $name): Project
    {
        $org = Organization::create(['name' => $name.' Inc', 'slug' => strtolower($name), 'owner_id' => $user->id]);
        $org->addMember($user, 'owner');

        return Project::create(['organization_id' => $org->id, 'name' => $name, 'platform' => 'laravel']);
    }

    protected function issueIn(Project $project, array $attributes = []): Issue
    {
        return Issue::create($attributes + [
            'project_id' => $project->id,
            'fingerprint' => sha1(uniqid('', true)),
            'type' => 'error',
            'level' => 'error',
            'title' => 'RuntimeException: Payment gateway timed out',
            'culprit' => 'app/Services/Checkout.php:42 in App\Services\Checkout::pay',
            'events_count' => 7,
            'environments' => ['production'],
        ]);
    }

    /**
     * Replace the ClickHouse-backed repository with canned data.
     */
    protected function fakeEvents(bool $failing = false): void
    {
        $fake = new class($this->app->make(ClickHouseClient::class), $failing) extends EventRepository
        {
            public function __construct(ClickHouseClient $ch, protected bool $failing)
            {
                parent::__construct($ch);
            }

            protected function event(int $issueId): array
            {
                if ($this->failing) {
                    throw new ClickHouseException('connection refused');
                }

                return [
                    'event_id' => McpTest::EVENT_ID,
                    'issue_id' => $issueId,
                    'type' => 'error',
                    'level' => 'error',
                    'timestamp' => '2026-11-17 10:00:00.000',
                    'environment' => 'production',
                    'message' => 'RuntimeException: Payment gateway timed out',
                    'tags' => ['module' => 'checkout'],
                    'payload' => [
                        'exception' => [
                            'type' => 'RuntimeException',
                            'value' => 'Payment gateway timed out',
                            'frames' => [
                                ['file' => 'app/Services/Checkout.php', 'line' => 42, 'function' => 'pay', 'in_app' => true, 'context' => ['pre' => [], 'line' => '$gateway->charge($order);', 'post' => []]],
                                ['file' => 'vendor/laravel/framework/src/Pipeline.php', 'line' => 9, 'function' => 'then', 'context' => ['pre' => [], 'line' => 'return $next();', 'post' => []]],
                            ],
                        ],
                        'request' => ['url' => 'https://shop.test/checkout', 'method' => 'POST'],
                        'breadcrumbs' => [['category' => 'query', 'message' => 'select * from orders']],
                    ],
                ];
            }

            public function latestIssueEvent(Project $project, int $issueId): ?array
            {
                return $this->event($issueId);
            }

            public function getEvent(Project $project, string $eventId): ?array
            {
                return $eventId === McpTest::EVENT_ID ? $this->event(1) : null;
            }

            public function listEvents(Project $project, array $filters, string $rangeKey, int $limit = 100): array
            {
                $row = ['event_id' => McpTest::EVENT_ID, 'type' => 'error', 'level' => 'error', 'timestamp' => '2026-11-17 10:00:00.000', 'message' => 'boom '.($filters['search'] ?? ''), 'release' => '', 'status_code' => 0];

                return ['rows' => [$row], 'has_more' => true, 'next' => $row['timestamp'].'|'.$row['event_id']];
            }

            public function projectSummary(Project $project, string $rangeKey): array
            {
                return ['errors' => 3, 'logs' => 10, 'console' => 0, 'users' => 2, 'issues' => 1, 'transactions' => 0, 'p95' => 0.0, 'p50' => 0.0, 'failed' => 0];
            }
        };
        $this->app->instance(EventRepository::class, $fake);
    }

    protected function rpc(string $method, array $params = [], ?string $token = null): TestResponse
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], ['Authorization' => 'Bearer '.($token ?? $this->token)]);
    }

    /**
     * Call a tool and return [decoded result or error text, isError].
     */
    protected function tool(string $name, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $name, 'arguments' => $arguments])->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [$result['isError'] ? $text : json_decode($text, true), $result['isError']];
    }

    public function test_requires_a_valid_token(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
            ->assertStatus(401)->assertHeader('WWW-Authenticate');
        $this->rpc('ping', [], 'lxp_'.str_repeat('a', 40))->assertStatus(401);
        // A project ingest key is not an access token.
        $this->rpc('ping', [], $this->project->public_key)->assertStatus(401);
    }

    public function test_initialize_negotiates_version_and_advertises_tools(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-03-26', 'capabilities' => [], 'clientInfo' => ['name' => 't', 'version' => '1']])
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-03-26')
            ->assertJsonPath('result.serverInfo.name', 'loggerxo')
            ->assertJsonStructure(['result' => ['capabilities' => ['tools'], 'instructions']]);

        $this->rpc('initialize', ['protocolVersion' => '1999-01-01'])->assertJsonPath('result.protocolVersion', '2025-11-25');
        $this->assertStringContainsString('"tools":{}', $this->rpc('initialize')->getContent());
    }

    public function test_notifications_get_no_body_and_unknown_methods_an_error(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], ['Authorization' => 'Bearer '.$this->token])
            ->assertStatus(202);
        $this->rpc('ping')->assertOk()->assertJsonPath('id', 1);
        $this->rpc('resources/list')->assertOk()->assertJsonPath('error.code', -32601);
        $this->rpc('tools/call', ['name' => 'drop_everything'])->assertOk()->assertJsonPath('error.code', -32602);
        $this->call('POST', '/mcp', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'], 'not json')
            ->assertStatus(400)->assertJsonPath('error.code', -32700);
    }

    public function test_lists_tools_with_schemas(): void
    {
        $tools = $this->rpc('tools/list')->assertOk()->json('result.tools');
        $this->assertEqualsCanonicalizing(
            ['list_projects', 'get_project_stats', 'list_issues', 'get_issue', 'list_issue_events', 'get_event', 'search_events'],
            array_column($tools, 'name')
        );
        foreach ($tools as $tool) {
            $this->assertSame('object', $tool['inputSchema']['type']);
            $this->assertTrue($tool['annotations']['readOnlyHint']);
        }
    }

    public function test_projects_and_issues_are_scoped_to_the_token_owner(): void
    {
        $this->issueIn($this->project);
        $this->issueIn($this->project, ['status' => 'resolved', 'title' => 'Old one']);
        $foreign = $this->projectFor(User::factory()->create(), 'Other');
        $foreignIssue = $this->issueIn($foreign);

        [$data] = $this->tool('list_projects');
        $this->assertSame(['Shop'], array_column($data['projects'], 'name'));
        $this->assertSame(1, $data['projects'][0]['open_issues']);

        [$data] = $this->tool('list_issues', ['project_id' => $this->project->id]);
        $this->assertSame(1, $data['total']);
        $this->assertSame('RuntimeException: Payment gateway timed out', $data['issues'][0]['title']);
        [$data] = $this->tool('list_issues', ['project_id' => $this->project->id, 'status' => 'all', 'query' => 'old']);
        $this->assertSame(['Old one'], array_column($data['issues'], 'title'));

        [$message, $isError] = $this->tool('list_issues', ['project_id' => $foreign->id]);
        $this->assertTrue($isError);
        $this->assertStringContainsString('No project', $message);
        [, $isError] = $this->tool('get_issue', ['issue_id' => $foreignIssue->id]);
        $this->assertTrue($isError);
        [, $isError] = $this->tool('get_event', ['project_id' => $foreign->id, 'event_id' => self::EVENT_ID]);
        $this->assertTrue($isError);
    }

    public function test_get_issue_returns_the_stack_trace_of_the_latest_event(): void
    {
        $issue = $this->issueIn($this->project);

        [$data, $isError] = $this->tool('get_issue', ['issue_id' => $issue->id]);
        $this->assertFalse($isError);
        $this->assertSame($issue->id, $data['issue']['issue_id']);
        $this->assertSame('open', $data['issue']['status']);
        $this->assertSame('RuntimeException', $data['event']['exception']['type']);
        $frames = $data['event']['exception']['frames'];
        $this->assertSame('$gateway->charge($order);', $frames[0]['context']['line']);
        $this->assertArrayNotHasKey('context', $frames[1], 'vendor frames carry no source context');
        $this->assertSame('POST', $data['event']['request']['method']);
        $this->assertArrayNotHasKey('message', $data['event']);
        $this->assertArrayNotHasKey('payload', $data['event']);
    }

    public function test_event_tools_page_and_validate_arguments(): void
    {
        $issue = $this->issueIn($this->project);

        [$data] = $this->tool('list_issue_events', ['issue_id' => $issue->id, 'limit' => 5]);
        $this->assertSame(self::EVENT_ID, $data['events'][0]['event_id']);
        $this->assertSame('2026-11-17 10:00:00.000|'.self::EVENT_ID, $data['next_cursor']);
        $this->assertArrayNotHasKey('release', $data['events'][0], 'empty columns are dropped');

        [$data] = $this->tool('search_events', ['project_id' => $this->project->id, 'query' => 'url:/checkout', 'levels' => ['error'], 'cursor' => $data['next_cursor']]);
        $this->assertSame('boom url:/checkout', $data['events'][0]['message']);

        [$data] = $this->tool('get_project_stats', ['project_id' => $this->project->id, 'range' => '7d']);
        $this->assertSame(3, $data['totals']['errors']);
        $this->assertSame(1, $data['issues_by_status']['open']);

        foreach ([
            ['search_events', ['project_id' => $this->project->id, 'range' => '90d']],
            ['search_events', ['project_id' => $this->project->id, 'cursor' => "x' OR 1=1"]],
            ['search_events', ['project_id' => $this->project->id, 'types' => ['transaction']]],
            ['get_event', ['project_id' => $this->project->id, 'event_id' => 'nope']],
            ['get_event', ['project_id' => $this->project->id, 'event_id' => '11111111-1111-4111-8111-111111111111']],
            ['list_issues', []],
        ] as [$name, $arguments]) {
            [, $isError] = $this->tool($name, $arguments);
            $this->assertTrue($isError, $name.' '.json_encode($arguments));
        }
    }

    public function test_event_store_outage_is_reported_as_a_tool_error(): void
    {
        $issue = $this->issueIn($this->project);
        $this->fakeEvents(failing: true);

        [$message, $isError] = $this->tool('get_issue', ['issue_id' => $issue->id]);
        $this->assertTrue($isError);
        $this->assertStringNotContainsString('connection refused', $message);
        [, $isError] = $this->tool('list_issues', ['project_id' => $this->project->id]);
        $this->assertFalse($isError);
    }

    public function test_tokens_are_created_and_revoked_from_account_settings(): void
    {
        $component = Livewire::actingAs($this->user)->test(Settings::class)
            ->set('token_name', 'Laptop')->call('createToken')->assertHasNoErrors();
        $plain = $component->get('new_token');
        $this->assertStringStartsWith('lxp_', $plain);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);
        $this->rpc('ping', [], $plain)->assertOk();
        $this->assertNotNull(ApiToken::findByPlain($plain)->last_used_at);

        $component->call('revokeToken', ApiToken::findByPlain($plain)->id);
        $this->rpc('ping', [], $plain)->assertStatus(401);

        // Someone else's token cannot be revoked.
        $other = ApiToken::findByPlain($this->token);
        Livewire::actingAs(User::factory()->create())->test(Settings::class)->call('revokeToken', $other->id);
        $this->assertNotNull($other->fresh());
    }
}
