<div @if ($live) wire:poll.5s @endif>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[260px]">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-500" />
            <input type="search" wire:model.live.debounce.500ms="q" placeholder="Search messages… try url:/checkout, user:jane@example.com, tag:module=orders, exception:TypeError" class="input pl-9">
        </div>
        <select wire:model.live="environment" class="select w-auto"><option value="">All environments</option>@foreach ($environments as $env)<option value="{{ $env }}">{{ $env }}</option>@endforeach</select>
        <select wire:model.live="logger" class="select w-auto"><option value="">All loggers</option>@foreach ($loggers as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach</select>
        <x-range-select wire:model.live="range" class="select w-auto" />
        <button wire:click="toggleLive" class="{{ $live ? 'btn-primary' : 'btn-secondary' }}">
            @if ($live)<span class="live-dot h-2 w-2 rounded-full bg-white"></span> Live @else <x-icon name="play" /> Live tail @endif
        </button>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
        <div class="flex items-center gap-1">
            <span class="mr-1 text-ink-500">Type</span>
            @foreach (['log' => 'Logs', 'console' => 'Console', 'error' => 'Errors'] as $t => $label)
                <button wire:click="toggleType('{{ $t }}')" class="rounded-md border px-2 py-1 font-medium transition {{ in_array($t, $types) ? 'border-brand-500/50 bg-brand-500/15 text-brand-200' : 'border-ink-700 bg-ink-900 text-ink-400 hover:text-ink-200' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-1">
            <span class="mr-1 text-ink-500">Level</span>
            @foreach ($allLevels as $l)
                <button wire:click="toggleLevel('{{ $l }}')" class="rounded-md border px-2 py-1 font-medium uppercase tracking-wide transition {{ in_array($l, $levels) ? 'border-brand-500/50 bg-brand-500/15 text-brand-200' : 'border-ink-700 bg-ink-900 text-ink-500 hover:text-ink-200' }}">{{ $l }}</button>
            @endforeach
            @if ($levels) <button wire:click="$set('levels', [])" class="ml-1 text-ink-500 hover:text-ink-200">clear</button> @endif
        </div>
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-[13px] text-red-200">ClickHouse query failed: {{ $error }}</div>
    @endif

    <div class="card mb-4">
        <div class="h-[120px] p-3" wire:ignore x-data="lxChart(@js($chart))" wire:key="logs-chart-{{ $chartVersion }}"><canvas x-ref="canvas"></canvas></div>
    </div>

    <div class="card overflow-hidden">
        @if (empty($rows))
            <x-empty icon="list" title="No log entries" body="Nothing matches these filters in the selected range." />
        @else
            <div class="divide-y divide-ink-800 font-mono text-[12.5px]">
                @foreach ($rows as $row)
                    @php $isOpen = $expanded === $row['event_id']; @endphp
                    <div wire:key="row-{{ $row['event_id'] }}">
                        <button wire:click="expand('{{ $row['event_id'] }}')" class="grid w-full grid-cols-[7.5rem_5.5rem_1fr] items-start gap-3 px-4 py-2 text-left hover:bg-ink-850/60 {{ $isOpen ? 'bg-ink-850/80' : '' }}">
                            <span class="whitespace-nowrap text-ink-500" title="{{ $row['timestamp'] }} UTC">{{ substr($row['timestamp'], 5, 14) }}</span>
                            <span><x-level :level="$row['level']" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-ink-100 {{ $isOpen ? 'whitespace-normal break-words' : '' }}">{{ $row['message'] }}</span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-ink-500">
                                    <x-type-badge :type="$row['type']" />
                                    @if ($row['logger'])<span>{{ $row['logger'] }}</span>@endif
                                    @if ($row['environment'])<span class="rounded bg-ink-800 px-1">{{ $row['environment'] }}</span>@endif
                                    @if ($row['transaction'])<span class="truncate">{{ $row['transaction'] }}</span>@elseif ($row['url'])<span class="truncate">{{ parse_url($row['url'], PHP_URL_PATH) }}</span>@endif
                                    @if ($row['user_email'] || $row['user_id'])<span>{{ $row['user_email'] ?: '#'.$row['user_id'] }}</span>@endif
                                    @if ($row['browser'])<span>{{ $row['browser'] }} · {{ $row['os'] }}</span>@endif
                                    @if ($row['issue_id'])<a href="{{ route('issues.show', [$project, $row['issue_id']]) }}" wire:click.stop class="text-brand-400 hover:text-brand-300">issue ↗</a>@endif
                                </span>
                            </span>
                        </button>
                        @if ($isOpen && $expandedEvent)
                            @php $p = $expandedEvent['payload']; @endphp
                            <div class="border-t border-ink-800 bg-ink-950/60 px-4 py-4 font-sans">
                                <div class="grid gap-4 lg:grid-cols-2">
                                    @if (! empty($p['exception']))
                                        <div class="lg:col-span-2">
                                            <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Exception</div>
                                            <div class="text-[13px]"><span class="font-semibold text-rose-300">{{ $p['exception']['type'] }}</span>: {{ $p['exception']['value'] }}</div>
                                            <ol class="mt-2 space-y-0.5 font-mono text-[11.5px]">
                                                @foreach (array_slice($p['exception']['frames'] ?? [], 0, 8) as $f)
                                                    <li class="{{ ! empty($f['in_app']) ? 'text-ink-200' : 'text-ink-500' }}">{{ $f['file'] ?? '' }}{{ isset($f['line']) ? ':'.$f['line'] : '' }} @if (! empty($f['function']))<span class="text-ink-500">in</span> {{ ($f['class'] ?? '') ? $f['class'].'::' : '' }}{{ $f['function'] }}@endif</li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    @endif
                                    @if (! empty($p['context']))
                                        <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Context</div><x-kv :data="$p['context']" /></div>
                                    @endif
                                    @if (! empty($p['request']))
                                        <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Request</div><x-kv :data="array_filter(['url' => $p['request']['url'] ?? null, 'method' => $p['request']['method'] ?? null, 'ip' => $p['request']['ip'] ?? null, 'route' => $p['request']['route'] ?? null])" /></div>
                                    @endif
                                    @if (! empty($p['user']))
                                        <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">User</div><x-kv :data="$p['user']" /></div>
                                    @endif
                                    @if (! empty($p['tags']))
                                        <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Tags</div><x-kv :data="$p['tags']" /></div>
                                    @endif
                                    @if (! empty($p['breadcrumbs']))
                                        <div class="lg:col-span-2">
                                            <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Breadcrumbs</div>
                                            <ul class="space-y-0.5 font-mono text-[11.5px] text-ink-300">
                                                @foreach (array_slice($p['breadcrumbs'], -10) as $c)<li><span class="text-ink-500">{{ $c['category'] ?? $c['type'] ?? '' }}</span> {{ $c['message'] ?? '' }}</li>@endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center gap-3 text-[11px] text-ink-500">
                                    <span>{{ $expandedEvent['event_id'] }}</span>
                                    @if ($expandedEvent['issue_id'])<a href="{{ route('issues.show', [$project, $expandedEvent['issue_id'], 'event' => $expandedEvent['event_id']]) }}" class="text-brand-400 hover:text-brand-300">Open full event in issue →</a>@endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($hasMore && ! $live)
                <div class="border-t border-ink-800 p-3 text-center"><button wire:click="loadMore('{{ $next }}')" class="btn-secondary btn-sm" wire:loading.attr="disabled">Load 100 older entries</button></div>
            @endif
        @endif
    </div>
</div>
