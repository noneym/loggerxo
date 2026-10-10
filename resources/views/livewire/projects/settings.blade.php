<div>
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight text-white">{{ $project->name }} settings</h1>
        <p class="text-[13px] text-ink-400">Keys, SDK setup, alerting and retention for this project.</p>
    </div>

    <div class="mb-5 flex items-center gap-1 border-b border-ink-800">
        @foreach (['general' => 'General', 'setup' => 'Keys & setup', 'alerts' => 'Alerts', 'danger' => 'Danger zone'] as $k => $l)
            <button wire:click="setTab('{{ $k }}')" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $l }}</button>
        @endforeach
    </div>

    {{-- GENERAL --}}
    @if ($tab === 'general')
        <form wire:submit="saveGeneral" class="card max-w-2xl space-y-5 p-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="label">Project name</label><input type="text" wire:model="name" class="input">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Platform</label><select wire:model="platform" class="select">@foreach ($platforms as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="label">Retention (days)</label><input type="number" min="1" max="{{ config('loggerxo.max_retention_days') }}" wire:model="retention_days" class="input"><p class="hint">Events older than this are deleted nightly. Max {{ config('loggerxo.max_retention_days') }} days.</p>@error('retention_days')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Rate limit (events / minute)</label><input type="number" min="0" wire:model="rate_limit_per_minute" class="input" placeholder="{{ config('loggerxo.ingest.rate_limit_per_minute') }} (default)"><p class="hint">Extra events are dropped with HTTP 429. 0 disables the limit.</p></div>
            </div>
            <div>
                <label class="label">Allowed browser origins</label>
                <textarea wire:model="allowed_origins" rows="3" class="input font-mono text-xs" placeholder="https://shop.example.com&#10;*.example.com"></textarea>
                <p class="hint">One per line. Leave empty to accept browser events from any site (the key is public in the page source anyway, but origins stop abuse).</p>
            </div>
            <label class="flex items-center gap-2 text-[13px] text-ink-200"><input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-ink-600 bg-ink-850 text-brand-600"> Project is active (uncheck to reject all incoming events)</label>
            <div class="flex justify-end border-t border-ink-800 pt-4"><button type="submit" class="btn-primary">Save changes</button></div>
        </form>
    @endif

    {{-- SETUP --}}
    @if ($tab === 'setup')
        <div class="space-y-4">
            <div class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="card-title">Project key</div>
                        <div class="mt-1 flex items-center gap-2"><code class="mono rounded bg-ink-950 px-2 py-1 text-brand-300">{{ $project->public_key }}</code><button onclick="lxCopy('{{ $project->public_key }}', this)" class="btn-ghost btn-sm">Copy</button></div>
                        <p class="hint">DSN form: <code class="text-ink-300">{{ $project->dsn() }}</code></p>
                    </div>
                    <button wire:click="regenerateKey" wire:confirm="Generate a new key? All SDKs using the old key stop sending within 5 minutes." class="btn-secondary btn-sm"><x-icon name="refresh" class="h-3.5 w-3.5" /> Regenerate</button>
                </div>
            </div>

            <div class="card" x-data="{ guide: '{{ $project->platform === 'browser' ? 'browser' : ($project->platform === 'other' ? 'http' : 'laravel') }}' }">
                <div class="flex items-center gap-1 border-b border-ink-800 px-3">
                    <button @click="guide = 'laravel'" class="tab" :class="guide === 'laravel' && 'active'">Laravel</button>
                    <button @click="guide = 'browser'" class="tab" :class="guide === 'browser' && 'active'">Browser</button>
                    <button @click="guide = 'http'" class="tab" :class="guide === 'http' && 'active'">HTTP API</button>
                </div>

                <div x-show="guide === 'laravel'" class="space-y-4 p-5">
                    <x-setup-step n="1" title="Install the package">
                        <p class="text-[13px] text-ink-300">Until the package is on Packagist, add the repository to your <code>composer.json</code> and require it:</p>
                        <x-code>{{ 'composer config repositories.loggerxo vcs https://github.com/noneym/loggerxo-laravel
composer require noneym/loggerxo-laravel:dev-main' }}</x-code>
                    </x-setup-step>
                    <x-setup-step n="2" title="Configure .env">
                        <x-code>{{ "LOGGERXO_DSN={$project->dsn()}
LOGGERXO_ENVIRONMENT=\${APP_ENV}
LOGGERXO_RELEASE=1.0.0
# optional: also stream every log line (debug and up) to Loggerxo
LOG_STACK=single,loggerxo
LOGGERXO_LOG_LEVEL=debug" }}</x-code>
                        <p class="text-[13px] text-ink-300">Exceptions are reported automatically once the package is installed. Add the <code>loggerxo</code> channel to <code>LOG_STACK</code> to ship log lines too, and set <code>LOGGERXO_TRACING=true</code> (default) for request timings.</p>
                    </x-setup-step>
                    <x-setup-step n="3" title="Send a test event">
                        <x-code>php artisan loggerxo:test</x-code>
                        <p class="text-[13px] text-ink-300">You can also call <code>Loggerxo::captureException($e)</code>, <code>Loggerxo::captureMessage('Hello', 'info')</code>, <code>Loggerxo::setUser([...])</code> and <code>Loggerxo::setTag('module', 'orders')</code> anywhere in your app.</p>
                    </x-setup-step>
                </div>

                <div x-show="guide === 'browser'" x-cloak class="space-y-4 p-5">
                    <x-setup-step n="1" title="Add one script tag">
                        <p class="text-[13px] text-ink-300">Place it as high as possible in <code>&lt;head&gt;</code> so early errors are captured too.</p>
                        <x-code>{!! e('<script src="'.$appUrl.'/sdk/loggerxo.js" data-key="'.$project->public_key.'" data-env="production" data-release="1.0.0"></script>') !!}</x-code>
                    </x-setup-step>
                    <x-setup-step n="2" title="Optional configuration">
                        <x-code>{{ "Loggerxo.setUser({ id: '42', email: 'jane@example.com' });
Loggerxo.setTag('plan', 'pro');
Loggerxo.captureMessage('Checkout opened', 'info');
Loggerxo.captureException(new Error('Manual capture'));

// or manual init instead of data-key:
Loggerxo.init({
  key: '{$project->public_key}',
  endpoint: '{$appUrl}',
  environment: 'production',
  captureConsole: ['error', 'warn'],   // console levels sent as events
  captureHttpErrors: true,             // fetch/XHR >= 500 or network failures
  tracing: true,                       // page load timing + web vitals
  ignoreErrors: [/ResizeObserver loop/],
});" }}</x-code>
                    </x-setup-step>
                    <x-setup-step n="3" title="Restrict origins (recommended)">
                        <p class="text-[13px] text-ink-300">The key is visible in your page source. Add your domains under <button wire:click="setTab('general')" class="text-brand-400 hover:text-brand-300">General → Allowed browser origins</button> so other sites cannot send events to this project.</p>
                    </x-setup-step>
                </div>

                <div x-show="guide === 'http'" x-cloak class="space-y-4 p-5">
                    <x-setup-step n="1" title="Endpoint">
                        <x-code>{{ "POST {$appUrl}/api/v1/envelope
X-Loggerxo-Key: {$project->public_key}
Content-Type: application/json
Content-Encoding: gzip   (optional)" }}</x-code>
                    </x-setup-step>
                    <x-setup-step n="2" title="Body">
                        <x-code>{{ '{
  "sdk": {"name": "my-app", "version": "1.0"},
  "environment": "production",
  "release": "2026.10.1",
  "events": [
    {
      "type": "error",                       // error | log | console | transaction
      "level": "error",                      // debug … emergency
      "timestamp": "2026-10-10T12:00:00Z",
      "message": "Optional message",
      "logger": "payments",
      "transaction": "GET /orders/{id}",
      "exception": {
        "type": "RuntimeException",
        "value": "Order not found",
        "frames": [{"file": "app/Http/OrderController.php", "line": 42, "function": "show", "in_app": true}]
      },
      "request": {"url": "https://…", "method": "GET", "headers": {}, "ip": "1.2.3.4"},
      "user": {"id": "9", "email": "jane@example.com"},
      "tags": {"module": "orders"},
      "context": {"anything": "you like"},
      "breadcrumbs": [{"type": "query", "category": "db", "message": "select …", "timestamp": "…"}],
      "fingerprint": ["optional", "custom-grouping"]
    },
    {
      "type": "transaction", "name": "GET /orders/{id}", "op": "http.server",
      "http_method": "GET", "status_code": 200, "duration_ms": 182.4,
      "measurements": {"db_time_ms": 41.2, "db_query_count": 7, "memory_mb": 18.5},
      "spans": [{"op": "db.query", "description": "select …", "start_ms": 10, "duration_ms": 1.2}]
    }
  ]
}' }}</x-code>
                        <p class="text-[13px] text-ink-300">Response is <code>202 {"accepted": n, "dropped": n}</code>. Up to 500 events per request, 2 MB body. Rate limit returns 429 with <code>Retry-After</code>.</p>
                    </x-setup-step>
                </div>
            </div>
        </div>
    @endif

    {{-- ALERTS --}}
    @if ($tab === 'alerts')
        <div class="grid gap-4 xl:grid-cols-2">
            <div class="space-y-4">
                <div class="card">
                    <div class="card-header"><span class="card-title">Channels</span>
                        <div class="flex gap-1"><button wire:click="newChannel('email')" class="btn-secondary btn-sm"><x-icon name="mail" class="h-3.5 w-3.5" /> E-mail</button><button wire:click="newChannel('telegram')" class="btn-secondary btn-sm"><x-icon name="send" class="h-3.5 w-3.5" /> Telegram</button></div>
                    </div>
                    @if ($showChannelForm)
                        <form wire:submit="saveChannel" class="space-y-3 border-b border-ink-800 bg-ink-850/50 p-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div><label class="label">Type</label><select wire:model.live="channelType" class="select" @disabled($channelId)><option value="email">E-mail</option><option value="telegram">Telegram</option></select></div>
                                <div><label class="label">Name</label><input type="text" wire:model="channelName" class="input">@error('channelName')<p class="error">{{ $message }}</p>@enderror</div>
                            </div>
                            @if ($channelType === 'email')
                                <div><label class="label">Recipients</label><textarea wire:model="channelEmails" rows="2" class="input" placeholder="dev@example.com, ops@example.com"></textarea>@error('channelEmails')<p class="error">{{ $message }}</p>@enderror</div>
                            @else
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div><label class="label">Chat ID</label><input type="text" wire:model="channelChatId" class="input" placeholder="-1001234567890"><p class="hint">Add your bot to the group, then send a message and read the chat id from <code>getUpdates</code>.</p>@error('channelChatId')<p class="error">{{ $message }}</p>@enderror</div>
                                    <div><label class="label">Bot token {{ config('loggerxo.telegram_bot_token') ? '(optional, overrides global bot)' : '' }}</label><input type="text" wire:model="channelBotToken" class="input font-mono text-xs" placeholder="123456:ABC-DEF…">@error('channelBotToken')<p class="error">{{ $message }}</p>@enderror</div>
                                </div>
                            @endif
                            <div class="flex justify-end gap-2"><button type="button" wire:click="$set('showChannelForm', false)" class="btn-ghost btn-sm">Cancel</button><button type="submit" class="btn-primary btn-sm">Save channel</button></div>
                        </form>
                    @endif
                    <ul class="divide-y divide-ink-800">
                        @forelse ($channels as $c)
                            <li class="flex items-center gap-3 px-4 py-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-800 text-ink-300"><x-icon :name="$c->type === 'email' ? 'mail' : 'send'" /></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 text-[13px] font-medium text-ink-100">{{ $c->name }} @if (! $c->is_active)<span class="badge border-ink-600 bg-ink-800 text-ink-400">paused</span>@endif</div>
                                    <div class="truncate text-[11px] text-ink-500">{{ $c->typeLabel() }} · {{ $c->summary() }}</div>
                                    @if ($c->last_error)<div class="truncate text-[11px] text-red-400">Last error: {{ $c->last_error }}</div>@endif
                                </div>
                                <button wire:click="testChannel({{ $c->id }})" class="btn-ghost btn-sm" title="Send test">Test</button>
                                <button wire:click="editChannel({{ $c->id }})" class="btn-ghost btn-sm">Edit</button>
                                <button wire:click="toggleChannel({{ $c->id }})" class="btn-ghost btn-sm">{{ $c->is_active ? 'Pause' : 'Resume' }}</button>
                                <button wire:click="deleteChannel({{ $c->id }})" wire:confirm="Delete this channel?" class="btn-ghost btn-sm text-red-300"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                            </li>
                        @empty
                            <li class="px-4 py-8 text-center text-[13px] text-ink-500">No channels yet. Add an e-mail or Telegram channel first.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-title">Rules</span><button wire:click="newRule" class="btn-secondary btn-sm" @disabled($channels->isEmpty())><x-icon name="plus" class="h-3.5 w-3.5" /> New rule</button></div>
                    @if ($showRuleForm)
                        <form wire:submit="saveRule" class="space-y-3 border-b border-ink-800 bg-ink-850/50 p-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div><label class="label">Name</label><input type="text" wire:model="ruleName" class="input">@error('ruleName')<p class="error">{{ $message }}</p>@enderror</div>
                                <div><label class="label">Trigger</label><select wire:model.live="ruleTrigger" class="select">@foreach ($triggers as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                            </div>
                            @if ($ruleTrigger === 'frequency')
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div><label class="label">More than N events</label><input type="number" min="1" wire:model="ruleThreshold" class="input"></div>
                                    <div><label class="label">Within minutes</label><input type="number" min="1" wire:model="ruleWindow" class="input"></div>
                                </div>
                            @elseif ($ruleTrigger === 'log_level')
                                <div><label class="label">Minimum level</label><select wire:model="ruleMinLevel" class="select">@foreach ($levels as $l)<option value="{{ $l }}">{{ ucfirst($l) }}</option>@endforeach</select></div>
                            @endif
                            <div class="grid gap-3 md:grid-cols-2">
                                <div><label class="label">Only these environments (optional)</label><input type="text" wire:model="ruleEnvironments" class="input" placeholder="production, staging"></div>
                                <div><label class="label">Cooldown per issue (minutes)</label><input type="number" min="0" wire:model="ruleCooldown" class="input"></div>
                            </div>
                            <div>
                                <label class="label">Event types (optional)</label>
                                <div class="flex gap-3 text-[13px] text-ink-200">@foreach (['error' => 'Errors', 'log' => 'Logs', 'console' => 'Console'] as $k => $l)<label class="flex items-center gap-1.5"><input type="checkbox" wire:model="ruleTypes" value="{{ $k }}" class="h-4 w-4 rounded border-ink-600 bg-ink-850 text-brand-600"> {{ $l }}</label>@endforeach</div>
                            </div>
                            <div>
                                <label class="label">Channels</label>
                                <div class="flex flex-wrap gap-3 text-[13px] text-ink-200">@foreach ($channels as $c)<label class="flex items-center gap-1.5"><input type="checkbox" wire:model="ruleChannels" value="{{ $c->id }}" class="h-4 w-4 rounded border-ink-600 bg-ink-850 text-brand-600"> {{ $c->name }}</label>@endforeach</div>
                                @error('ruleChannels')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex justify-end gap-2"><button type="button" wire:click="$set('showRuleForm', false)" class="btn-ghost btn-sm">Cancel</button><button type="submit" class="btn-primary btn-sm">Save rule</button></div>
                        </form>
                    @endif
                    <ul class="divide-y divide-ink-800">
                        @forelse ($rules as $r)
                            <li class="flex items-center gap-3 px-4 py-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-800 text-ink-300"><x-icon name="bell" /></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 text-[13px] font-medium text-ink-100">{{ $r->name }} @if (! $r->is_active)<span class="badge border-ink-600 bg-ink-800 text-ink-400">paused</span>@endif</div>
                                    <div class="text-[11px] text-ink-500">{{ $r->describe() }} · {{ count((array) $r->channel_ids) }} {{ Str::plural('channel', count((array) $r->channel_ids)) }} · cooldown {{ $r->cooldown_minutes }}m @if ($r->last_triggered_at) · last fired <x-time :value="$r->last_triggered_at" /> @endif</div>
                                </div>
                                <button wire:click="editRule({{ $r->id }})" class="btn-ghost btn-sm">Edit</button>
                                <button wire:click="toggleRule({{ $r->id }})" class="btn-ghost btn-sm">{{ $r->is_active ? 'Pause' : 'Resume' }}</button>
                                <button wire:click="deleteRule({{ $r->id }})" wire:confirm="Delete this rule?" class="btn-ghost btn-sm text-red-300"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                            </li>
                        @empty
                            <li class="px-4 py-8 text-center text-[13px] text-ink-500">No rules yet. A typical setup: “new issue” + “regression” to your team channel, and “level ≥ critical” to Telegram.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card self-start">
                <div class="card-header"><span class="card-title">Recent notifications</span></div>
                <ul class="divide-y divide-ink-800">
                    @forelse ($notifications as $n)
                        <li class="px-4 py-2.5 text-[13px]">
                            <div class="flex items-center gap-2"><span class="badge {{ $n->status === 'sent' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-red-500/30 bg-red-500/10 text-red-300' }}">{{ $n->status }}</span><span class="text-ink-400">{{ $n->channel?->name ?? $n->channel_type }}</span><span class="ml-auto text-[11px] text-ink-500"><x-time :value="$n->created_at" /></span></div>
                            <div class="mt-0.5 truncate text-ink-200">@if ($n->issue)<a href="{{ $n->issue->url() }}" class="hover:text-brand-300">{{ $n->subject }}</a>@else{{ $n->subject }}@endif</div>
                            @if ($n->error)<div class="truncate text-[11px] text-red-400">{{ $n->error }}</div>@endif
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-[13px] text-ink-500">Nothing sent yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif

    {{-- DANGER --}}
    @if ($tab === 'danger')
        <div class="card max-w-2xl border-red-500/30 p-6">
            <div class="card-title text-red-300">Delete project</div>
            <p class="mt-2 text-[13px] text-ink-300">Removes the project, its issues, alert rules and keys. Events in ClickHouse are purged by the nightly retention job. This cannot be undone.</p>
            <button wire:click="deleteProject" wire:confirm="Really delete {{ $project->name }}? Type OK to confirm." class="btn-danger mt-4"><x-icon name="trash" /> Delete {{ $project->name }}</button>
        </div>
    @endif
</div>
