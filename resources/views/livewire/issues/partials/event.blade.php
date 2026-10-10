@php
    $p = $event['payload'] ?? [];
    $exception = $p['exception'] ?? null;
    $chain = array_merge($exception ? [$exception] : [], $exception['chain'] ?? []);
@endphp
<div class="grid gap-4 xl:grid-cols-3">
    <div class="space-y-4 xl:col-span-2">
        {{-- Event nav --}}
        <div class="card flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-[13px]">
            <div class="flex items-center gap-2 text-ink-300">
                <span class="font-mono text-[11px] text-ink-500">{{ $event['event_id'] }}</span>
                <span>·</span>
                <span title="{{ $event['timestamp'] }} UTC">{{ \Carbon\CarbonImmutable::parse($event['timestamp'], 'UTC')->format('M j, Y H:i:s.v') }} UTC</span>
                <span>·</span><x-time :value="$event['timestamp']" />
            </div>
            <div class="flex items-center gap-1">
                <button wire:click="showEvent({{ $neighbours['newer'] ? "'".$neighbours['newer']."'" : 'null' }})" @disabled(! $neighbours['newer']) class="btn-secondary btn-sm"><x-icon name="chevron-left" class="h-3.5 w-3.5" /> Newer</button>
                <button wire:click="showEvent(null)" class="btn-secondary btn-sm">Latest</button>
                <button wire:click="showEvent({{ $neighbours['older'] ? "'".$neighbours['older']."'" : 'null' }})" @disabled(! $neighbours['older']) class="btn-secondary btn-sm">Older <x-icon name="chevron-right" class="h-3.5 w-3.5" /></button>
            </div>
        </div>

        {{-- Message --}}
        <div class="card p-4">
            <div class="flex items-center gap-2"><x-level :level="$event['level']" /><x-type-badge :type="$event['type']" />@if ($event['logger'])<span class="font-mono text-[11px] text-ink-500">{{ $event['logger'] }}</span>@endif</div>
            <pre class="mt-3 whitespace-pre-wrap break-words font-sans text-[14px] leading-relaxed text-ink-100">{{ $event['message'] }}</pre>
        </div>

        {{-- Exception + stack --}}
        @foreach ($chain as $i => $ex)
            <div class="card" x-data="{ all: false }">
                <div class="card-header flex-wrap">
                    <div class="min-w-0">
                        <span class="card-title">{{ $i === 0 ? 'Exception' : 'Caused by' }}</span>
                        <div class="mt-0.5 break-words text-[13px]"><span class="font-semibold text-rose-300">{{ $ex['type'] }}</span><span class="text-ink-300">: {{ $ex['value'] }}</span></div>
                        @if (! empty($ex['code'])) <div class="text-[11px] text-ink-500">code {{ $ex['code'] }}</div> @endif
                    </div>
                    @if (count($ex['frames'] ?? []) > 0)
                        <button @click="all = !all" class="btn-ghost btn-sm shrink-0" x-text="all ? 'In-app frames only' : 'Show all {{ count($ex['frames']) }} frames'"></button>
                    @endif
                </div>
                @if (empty($ex['frames']))
                    <div class="px-4 py-6 text-center text-[13px] text-ink-500">No stack trace was sent with this event.</div>
                @else
                    <ol class="divide-y divide-ink-800">
                        @foreach ($ex['frames'] as $fi => $frame)
                            @php $inApp = ! empty($frame['in_app']); $hasCtx = ! empty($frame['context']['line']); @endphp
                            <li x-data="{ open: {{ $fi === 0 || ($inApp && $fi < 3 && $hasCtx) ? 'true' : 'false' }} }" x-show="all || {{ $inApp ? 'true' : 'false' }} || {{ $fi === 0 ? 'true' : 'false' }}">
                                <button @click="open = !open" class="flex w-full items-center gap-2 px-4 py-2 text-left hover:bg-ink-850/60">
                                    <span class="w-5 text-right font-mono text-[11px] text-ink-600">{{ $fi + 1 }}</span>
                                    <span class="flex-1 truncate font-mono text-[12px] {{ $inApp ? 'text-ink-100' : 'text-ink-500' }}">
                                        {{ $frame['file'] ?? '<unknown>' }}@if (isset($frame['line']))<span class="text-ink-500">:{{ $frame['line'] }}</span>@endif
                                        @if (! empty($frame['function'])) <span class="text-ink-400">in</span> <span class="{{ $inApp ? 'text-brand-300' : 'text-ink-400' }}">{{ ! empty($frame['class']) ? $frame['class'].'::' : '' }}{{ $frame['function'] }}</span>@endif
                                    </span>
                                    @if ($inApp) <span class="badge border-brand-500/30 bg-brand-500/10 text-brand-300">app</span> @endif
                                    @if ($hasCtx) <x-icon name="chevron-down" class="h-3.5 w-3.5 text-ink-500 transition" x-bind:class="open && 'rotate-180'" /> @endif
                                </button>
                                @if ($hasCtx)
                                    <div x-cloak x-show="open" class="border-t border-ink-800 bg-ink-950 py-2">
                                        @php $start = ($frame['line'] ?? 0) - count($frame['context']['pre'] ?? []); @endphp
                                        @foreach ($frame['context']['pre'] ?? [] as $pi => $l)
                                            <div class="frame-line text-ink-500"><span class="pr-3 text-right text-ink-600 select-none">{{ $start + $pi }}</span><span class="whitespace-pre">{{ $l }}</span></div>
                                        @endforeach
                                        <div class="frame-line bg-rose-500/10 text-rose-200"><span class="pr-3 text-right text-rose-400 select-none">{{ $frame['line'] ?? '' }}</span><span class="whitespace-pre">{{ $frame['context']['line'] }}</span></div>
                                        @foreach ($frame['context']['post'] ?? [] as $pi => $l)
                                            <div class="frame-line text-ink-500"><span class="pr-3 text-right text-ink-600 select-none">{{ ($frame['line'] ?? 0) + $pi + 1 }}</span><span class="whitespace-pre">{{ $l }}</span></div>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endforeach

        {{-- Breadcrumbs --}}
        @if (! empty($p['breadcrumbs']))
            <div class="card">
                <div class="card-header"><span class="card-title">Breadcrumbs</span><span class="text-xs text-ink-500">{{ count($p['breadcrumbs']) }} entries, newest last</span></div>
                <ol class="divide-y divide-ink-800">
                    @foreach ($p['breadcrumbs'] as $crumb)
                        @php
                            $icon = match ($crumb['type'] ?? '') { 'http' => 'globe', 'query', 'db' => 'layers', 'navigation' => 'chevron-right', 'ui' => 'play', 'console' => 'terminal', 'log' => 'list', 'job', 'queue' => 'zap', 'error' => 'bug', default => 'clock' };
                            $lvl = $crumb['level'] ?? 'info';
                        @endphp
                        <li class="flex items-start gap-3 px-4 py-2 text-[12.5px]">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded bg-ink-800 text-ink-400"><x-icon :name="$icon" class="h-3 w-3" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[11px] text-ink-500">{{ $crumb['category'] ?? $crumb['type'] ?? '' }}</span>
                                    @if (in_array($lvl, ['error', 'critical', 'warning'])) <x-level :level="$lvl" /> @endif
                                </div>
                                <div class="break-words text-ink-200">{{ $crumb['message'] ?? '' }}</div>
                                @if (! empty($crumb['data']))
                                    <details class="mt-1"><summary class="cursor-pointer text-[11px] text-ink-500 hover:text-ink-300">data</summary><pre class="code-block mt-1 text-[11px]">{{ json_encode($crumb['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>
                                @endif
                            </div>
                            <span class="shrink-0 font-mono text-[11px] text-ink-500">{{ isset($crumb['timestamp']) ? substr($crumb['timestamp'], 11, 12) : '' }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        {{-- Request --}}
        @if (! empty($p['request']))
            <div class="card">
                <div class="card-header"><span class="card-title">Request</span><span class="font-mono text-xs text-ink-300">{{ $p['request']['method'] ?? '' }} {{ $p['request']['url'] ?? '' }}</span></div>
                <div class="grid gap-4 p-4 md:grid-cols-2">
                    @foreach (['headers' => 'Headers', 'query' => 'Query', 'data' => 'Body'] as $k => $label)
                        @if (! empty($p['request'][$k]))
                            <div class="{{ $k === 'headers' ? 'md:col-span-2' : '' }}">
                                <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">{{ $label }}</div>
                                <x-kv :data="$p['request'][$k]" />
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Context --}}
        @if (! empty($p['context']))
            <div class="card">
                <div class="card-header"><span class="card-title">Additional context</span></div>
                <div class="p-4"><x-kv :data="$p['context']" /></div>
            </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="space-y-4">
        <div class="card p-4 text-[13px]">
            <div class="card-title mb-3">Tags</div>
            <dl class="space-y-1.5">
                @foreach (array_filter(['environment' => $event['environment'], 'release' => $event['release'], 'platform' => $event['platform'], 'server' => $event['server_name'], 'transaction' => $event['transaction'], 'browser' => $event['browser'], 'os' => $event['os'], 'device' => $event['device'], 'sdk' => $event['sdk'], 'logger' => $event['logger']]) as $k => $v)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-ink-850 px-2.5 py-1.5"><dt class="text-ink-400">{{ $k }}</dt><dd class="truncate font-mono text-[12px] text-ink-100" title="{{ $v }}">{{ $v }}</dd></div>
                @endforeach
                @foreach ((array) ($event['tags'] ?? []) as $k => $v)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-brand-500/10 px-2.5 py-1.5"><dt class="text-brand-300">{{ $k }}</dt><dd class="truncate font-mono text-[12px] text-ink-100" title="{{ $v }}">{{ $v }}</dd></div>
                @endforeach
            </dl>
        </div>

        @if (! empty($p['user']) || $event['user_ip'])
            <div class="card p-4 text-[13px]">
                <div class="card-title mb-3">User</div>
                <dl class="space-y-1.5">
                    @foreach (array_filter($p['user'] ?? []) as $k => $v)
                        <div class="flex items-center justify-between gap-3"><dt class="text-ink-400">{{ $k }}</dt><dd class="truncate font-mono text-[12px] text-ink-100">{{ $v }}</dd></div>
                    @endforeach
                </dl>
                @if (! empty($p['user']['id']) || ! empty($p['user']['email']))
                    <a href="{{ route('logs.index', [$project, 'q' => 'user:'.($p['user']['email'] ?? $p['user']['id'])]) }}" class="mt-3 inline-flex items-center gap-1 text-xs text-brand-400 hover:text-brand-300">All events from this user <x-icon name="chevron-right" class="h-3 w-3" /></a>
                @endif
            </div>
        @endif

        @if ($event['url'])
            <div class="card p-4 text-[13px]">
                <div class="card-title mb-2">URL</div>
                <div class="break-all font-mono text-[12px] text-ink-200">{{ $event['url'] }}</div>
                @if ($event['user_agent']) <div class="mt-3 text-[11px] leading-relaxed text-ink-500">{{ $event['user_agent'] }}</div> @endif
            </div>
        @endif

        <div class="card p-4 text-[13px]">
            <div class="card-title mb-2">Raw event</div>
            <details><summary class="cursor-pointer text-xs text-ink-400 hover:text-ink-200">Show JSON</summary><pre class="code-block mt-2 max-h-96 text-[11px]">{{ json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details>
        </div>
    </div>
</div>
