<div>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('performance.index', [$project, 'range' => $range]) }}" class="inline-flex items-center gap-1 text-xs text-ink-400 hover:text-ink-100"><x-icon name="chevron-left" class="h-3.5 w-3.5" /> Performance</a>
            <h1 class="mt-1 break-all font-mono text-lg font-semibold text-white">{{ $name }}</h1>
            <p class="text-[13px] text-ink-400">{{ $stats['op'] ?? '' }} · {{ number_format($stats['users'] ?? 0) }} distinct users</p>
        </div>
        <x-range-select wire:model.live="range" />
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-[13px] text-red-200">ClickHouse query failed: {{ $error }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Requests" :value="$stats['count'] ?? 0" tone="emerald" icon="gauge" />
        <x-stat label="p50" :value="round($stats['p50'] ?? 0)" suffix="ms" tone="sky" />
        <x-stat label="p95" :value="round($stats['p95'] ?? 0)" suffix="ms" tone="violet" />
        <x-stat label="p99" :value="round($stats['p99'] ?? 0)" suffix="ms" tone="amber" />
        <x-stat label="Failed" :value="$stats['failed'] ?? 0" tone="red" />
        <x-stat label="DB avg" :value="round($stats['db_avg'] ?? 0)" suffix="ms" :hint="($stats['queries_avg'] ?? 0) ? number_format($stats['queries_avg'], 1).' queries avg' : null" tone="zinc" />
    </div>

    <div class="card mt-4">
        <div class="card-header"><span class="card-title">Latency & throughput</span></div>
        <div class="h-[220px] p-4" wire:ignore x-data="lxChart(@js($chart))" wire:key="tx-chart-{{ $chartVersion }}"><canvas x-ref="canvas"></canvas></div>
    </div>

    @if (! empty($measurements))
        <div class="card mt-4">
            <div class="card-header"><span class="card-title">Measurements</span></div>
            <div class="grid grid-cols-2 gap-px bg-ink-800 md:grid-cols-4 xl:grid-cols-6">
                @foreach ($measurements as $m)
                    <div class="bg-ink-900 px-4 py-3"><div class="truncate text-[11px] uppercase tracking-wider text-ink-500">{{ str_replace('_', ' ', $m['k']) }}</div><div class="text-base font-semibold text-white">{{ number_format((float) $m['avg'], str_ends_with($m['k'], '_ms') || str_ends_with($m['k'], '_mb') ? 0 : 3) }}</div><div class="text-[11px] text-ink-500">p75 {{ number_format((float) $m['p75'], str_ends_with($m['k'], '_ms') ? 0 : 3) }}</div></div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-5">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <span class="card-title">Samples</span>
                <select wire:model.live="sort" class="select w-auto py-1 text-xs"><option value="slowest">Slowest</option><option value="latest">Latest</option><option value="failed">Failed first</option></select>
            </div>
            <ul class="max-h-[640px] divide-y divide-ink-800 overflow-y-auto">
                @forelse ($samples as $s)
                    <li>
                        <button wire:click="$set('sampleId', '{{ $s['event_id'] }}')" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-ink-850/60 {{ $sampleId === $s['event_id'] ? 'bg-ink-850' : '' }}">
                            <span class="w-20 text-right font-mono text-[13px] {{ $s['duration_ms'] > 1000 ? 'text-amber-300' : 'text-ink-100' }}">{{ number_format($s['duration_ms']) }} ms</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] text-ink-300">{{ $s['url'] ?: $s['environment'] }}</span>
                                <span class="block text-[11px] text-ink-500"><x-time :value="$s['timestamp']" /> · {{ $s['environment'] }} @if ($s['db_query_count']) · {{ $s['db_query_count'] }} q / {{ number_format($s['db_time_ms']) }} ms @endif @if ($s['user_id']) · #{{ $s['user_id'] }} @endif</span>
                            </span>
                            @if ($s['status_code'])<span class="badge {{ $s['status_code'] >= 500 ? 'border-red-500/30 bg-red-500/10 text-red-300' : ($s['status_code'] >= 400 ? 'border-amber-500/30 bg-amber-500/10 text-amber-300' : 'border-ink-600 bg-ink-800 text-ink-300') }}">{{ $s['status_code'] }}</span>@endif
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-[13px] text-ink-500">No samples in range.</li>
                @endforelse
            </ul>
        </div>
        <div class="card xl:col-span-3">
            @if (! $sample)
                <x-empty icon="activity" title="Select a sample" body="Pick a request on the left to see its span waterfall, measurements and context." />
            @else
                @php $p = $sample['payload']; $total = max(1, (float) $sample['duration_ms']); $spans = $p['spans'] ?? []; @endphp
                <div class="card-header">
                    <div class="min-w-0"><span class="card-title">Trace</span><div class="truncate font-mono text-[12px] text-ink-300">{{ $sample['http_method'] }} {{ $sample['url'] ?: $sample['name'] }}</div></div>
                    <div class="text-right text-[12px] text-ink-400"><div class="font-mono text-ink-100">{{ number_format($sample['duration_ms'], 1) }} ms</div><div>{{ \Carbon\CarbonImmutable::parse($sample['timestamp'], 'UTC')->format('M j H:i:s') }} UTC</div></div>
                </div>
                <div class="p-4">
                    <div class="relative mb-2 h-6 rounded bg-ink-850"><div class="absolute inset-y-0 left-0 rounded bg-emerald-500/40" style="width:100%"></div><span class="absolute left-2 top-1 text-[11px] font-medium text-emerald-100">{{ $sample['name'] }} · {{ number_format($sample['duration_ms']) }} ms</span></div>
                    @if (empty($spans))
                        <div class="py-6 text-center text-[13px] text-ink-500">No spans recorded for this transaction.</div>
                    @else
                        <ol class="space-y-1">
                            @foreach ($spans as $span)
                                @php $left = min(100, max(0, ($span['start_ms'] ?? 0) / $total * 100)); $width = max(0.5, min(100 - $left, ($span['duration_ms'] ?? 0) / $total * 100)); $db = str_starts_with($span['op'] ?? '', 'db'); @endphp
                                <li class="group relative h-7 rounded bg-ink-850/60" title="{{ $span['description'] ?? '' }}">
                                    <div class="absolute inset-y-1 rounded {{ $db ? 'bg-sky-500/50' : (str_starts_with($span['op'] ?? '', 'http') ? 'bg-amber-500/50' : 'bg-brand-500/50') }}" style="left: {{ $left }}%; width: {{ $width }}%"></div>
                                    <div class="relative flex h-full items-center gap-2 px-2 font-mono text-[11px]">
                                        <span class="w-24 shrink-0 truncate text-ink-400">{{ $span['op'] ?? 'span' }}</span>
                                        <span class="min-w-0 flex-1 truncate text-ink-100">{{ $span['description'] ?? '' }}</span>
                                        <span class="shrink-0 text-ink-300">{{ number_format($span['duration_ms'] ?? 0, 1) }} ms</span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        @if (! empty($p['measurements'])) <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Measurements</div><x-kv :data="$p['measurements']" /></div> @endif
                        <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Context</div><x-kv :data="array_filter(['environment' => $sample['environment'], 'release' => $sample['release'], 'server' => $sample['server_name'], 'status' => $sample['status_code'], 'user' => $sample['user_id'] ?: null, 'browser' => trim($sample['browser'].' '.$sample['os']), 'memory' => $sample['memory_mb'] ? $sample['memory_mb'].' MB' : null])" /></div>
                        @if (! empty($p['request'])) <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Request</div><x-kv :data="array_filter(['url' => $p['request']['url'] ?? null, 'method' => $p['request']['method'] ?? null, 'ip' => $p['request']['ip'] ?? null, 'route' => $p['request']['route'] ?? null])" /></div> @endif
                        @if (! empty($p['breadcrumbs']))
                            <div><div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Breadcrumbs</div><ul class="space-y-0.5 font-mono text-[11.5px] text-ink-300">@foreach (array_slice($p['breadcrumbs'], -12) as $c)<li><span class="text-ink-500">{{ $c['category'] ?? '' }}</span> {{ $c['message'] ?? '' }}</li>@endforeach</ul></div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
