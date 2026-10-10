<div>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[220px]">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-500" />
            <input type="search" wire:model.live.debounce.400ms="q" placeholder="Filter by route / transaction name" class="input pl-9">
        </div>
        <select wire:model.live="op" class="select w-auto"><option value="">All operations</option><option value="http.server">HTTP requests</option><option value="pageload">Browser page loads</option><option value="queue.job">Queue jobs</option><option value="console.command">Console commands</option></select>
        <select wire:model.live="sort" class="select w-auto"><option value="p95">Sort: p95</option><option value="p50">Sort: p50</option><option value="count">Sort: throughput</option><option value="total">Sort: total time</option><option value="failures">Sort: failure rate</option></select>
        <x-range-select wire:model.live="range" />
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-[13px] text-red-200">ClickHouse query failed: {{ $error }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Transactions" :value="$summary['transactions']" tone="emerald" icon="gauge" />
        <x-stat label="p50" :value="$summary['p50'] ?: '–'" suffix="{{ $summary['p50'] ? 'ms' : '' }}" tone="sky" icon="clock" />
        <x-stat label="p95" :value="$summary['p95'] ?: '–'" suffix="{{ $summary['p95'] ? 'ms' : '' }}" tone="violet" icon="clock" />
        <x-stat label="Failed (5xx)" :value="$summary['failed']" tone="red" icon="warning" />
    </div>

    <div class="card mt-4">
        <div class="card-header"><span class="card-title">Latency & throughput</span></div>
        <div class="h-[220px] p-4" wire:ignore x-data="lxChart(@js($chart))" wire:key="perf-chart-{{ $chartVersion }}"><canvas x-ref="canvas"></canvas></div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><span class="card-title">Transactions</span><span class="text-xs text-ink-500">{{ count($groups) }} shown</span></div>
            @if (empty($groups))
                <x-empty icon="gauge" title="No transactions yet" body="Enable tracing in the SDK to see request timings here." />
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Transaction</th><th class="text-right">Requests</th><th class="text-right">p50</th><th class="text-right">p95</th><th class="text-right">Max</th><th class="text-right">DB</th><th class="text-right">5xx</th></tr></thead>
                        <tbody>
                        @foreach ($groups as $g)
                            <tr>
                                <td>
                                    <a href="{{ route('performance.show', [$project, 'name' => $g['name'], 'range' => $range]) }}" class="font-mono text-[12.5px] text-ink-100 hover:text-brand-300">
                                        @if ($g['http_method'] && ! str_starts_with($g['name'], $g['http_method'].' '))<span class="mr-1 text-ink-500">{{ $g['http_method'] }}</span>@endif{{ $g['name'] }}
                                    </a>
                                    <div class="text-[11px] text-ink-500">{{ $g['op'] }}</div>
                                </td>
                                <td class="text-right text-ink-200">{{ number_format($g['count']) }}</td>
                                <td class="text-right text-ink-200">{{ number_format($g['p50']) }} ms</td>
                                <td class="text-right font-medium {{ $g['p95'] > 1000 ? 'text-amber-300' : 'text-ink-100' }}">{{ number_format($g['p95']) }} ms</td>
                                <td class="text-right text-ink-400">{{ number_format($g['max']) }} ms</td>
                                <td class="text-right text-ink-400">{{ $g['db_avg'] ? number_format($g['db_avg']).' ms · '.number_format($g['queries_avg'], 1).' q' : '–' }}</td>
                                <td class="text-right {{ $g['failure_rate'] > 0 ? 'text-red-300' : 'text-ink-500' }}">{{ number_format($g['failure_rate'] * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="space-y-4">
            @if ($vitals)
                <div class="card">
                    <div class="card-header"><span class="card-title">Web vitals (p75)</span><span class="text-xs text-ink-500">{{ number_format($vitals['pageloads']) }} loads</span></div>
                    <div class="grid grid-cols-2 gap-px bg-ink-800">
                        @foreach ([['LCP', $vitals['lcp'], 'ms', 2500, 4000], ['FCP', $vitals['fcp'], 'ms', 1800, 3000], ['TTFB', $vitals['ttfb'], 'ms', 800, 1800], ['CLS', $vitals['cls'], '', 0.1, 0.25], ['Load', $vitals['load'], 'ms', 3000, 6000]] as [$k, $v, $u, $good, $poor])
                            @php $v = (float) $v; $tone = $v <= $good ? 'text-emerald-300' : ($v <= $poor ? 'text-amber-300' : 'text-red-300'); @endphp
                            <div class="bg-ink-900 px-4 py-3"><div class="text-[11px] uppercase tracking-wider text-ink-500">{{ $k }}</div><div class="text-lg font-semibold {{ $tone }}">{{ $u === 'ms' ? number_format($v) : number_format($v, 3) }}<span class="ml-0.5 text-xs text-ink-500">{{ $u }}</span></div></div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="card-header"><span class="card-title">Slowest DB queries</span></div>
                @if (empty($slowQueries))
                    <div class="px-4 py-8 text-center text-[13px] text-ink-500">No query spans recorded.</div>
                @else
                    <ul class="divide-y divide-ink-800">
                        @foreach ($slowQueries as $qrow)
                            <li class="px-4 py-2.5">
                                <div class="truncate font-mono text-[11.5px] text-ink-200" title="{{ $qrow['query'] }}">{{ $qrow['query'] }}</div>
                                <div class="text-[11px] text-ink-500">avg {{ number_format($qrow['avg_ms'], 1) }} ms · max {{ number_format($qrow['max_ms'], 1) }} ms · {{ number_format($qrow['count']) }}×</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
