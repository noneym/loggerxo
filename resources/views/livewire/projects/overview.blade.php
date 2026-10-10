<div>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-white">{{ $project->name }}</h1>
            <p class="text-[13px] text-ink-400">{{ $project->platformLabel() }} · {{ $openCount }} open {{ Str::plural('issue', $openCount) }}
                @if ($project->last_event_at) · last event <x-time :value="$project->last_event_at" /> @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-range-select wire:model.live="range" />
            <a href="{{ route('projects.settings', [$project, 'tab' => 'setup']) }}" class="btn-secondary"><x-icon name="code" /> Setup</a>
        </div>
    </div>

    @if (! $project->last_event_at)
        <div class="card mb-4 flex flex-col items-start gap-3 border-brand-500/30 bg-brand-500/5 p-5 md:flex-row md:items-center">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-600/20 text-brand-300"><x-icon name="sparkles" class="h-5 w-5" /></span>
            <div class="flex-1">
                <div class="text-[15px] font-semibold text-white">Waiting for your first event</div>
                <div class="text-[13px] text-ink-300">Install the SDK with your project key. This page fills up as soon as the first event arrives.</div>
            </div>
            <a href="{{ route('projects.settings', [$project, 'tab' => 'setup']) }}" class="btn-primary">Open setup guide</a>
        </div>
    @endif

    @if ($error)
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-[13px] text-red-200">ClickHouse query failed: {{ $error }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Errors" :value="$summary['errors']" tone="red" icon="bug" />
        <x-stat label="Logs" :value="$summary['logs']" tone="sky" icon="list" />
        <x-stat label="Console" :value="$summary['console']" tone="violet" icon="terminal" />
        <x-stat label="Users affected" :value="$summary['users']" tone="amber" icon="users" />
        <x-stat label="Transactions" :value="$summary['transactions']" tone="emerald" icon="gauge" />
        <x-stat label="p95 latency" :value="$summary['p95'] ?: '–'" suffix="{{ $summary['p95'] ? 'ms' : '' }}" tone="emerald" icon="clock" />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-5">
        <div class="card xl:col-span-3">
            <div class="card-header"><span class="card-title">Events</span><a href="{{ route('logs.index', $project) }}" class="text-xs text-ink-400 hover:text-ink-100">Explore logs →</a></div>
            <div class="h-[240px] p-4" wire:ignore x-data="lxChart(@js($eventChart))" wire:key="ev-{{ $chartVersion }}"><canvas x-ref="canvas"></canvas></div>
        </div>
        <div class="card xl:col-span-2">
            <div class="card-header"><span class="card-title">Response time</span><a href="{{ route('performance.index', $project) }}" class="text-xs text-ink-400 hover:text-ink-100">Performance →</a></div>
            <div class="h-[240px] p-4" wire:ignore x-data="lxChart(@js($txChart))" wire:key="tx-{{ $chartVersion }}"><canvas x-ref="canvas"></canvas></div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-5">
        <div class="card xl:col-span-3">
            <div class="card-header"><span class="card-title">Most recent open issues</span><a href="{{ route('issues.index', $project) }}" class="text-xs text-ink-400 hover:text-ink-100">All issues →</a></div>
            @if ($topIssues->isEmpty())
                <x-empty icon="check" title="No open issues" body="New errors and grouped log problems will show up here." />
            @else
                <ul class="divide-y divide-ink-800">
                    @foreach ($topIssues as $issue)
                        <li class="flex items-center gap-4 px-4 py-3 hover:bg-ink-850/60">
                            <div class="min-w-0 flex-1">
                                <a href="{{ $issue->url() }}" class="flex items-center gap-2">
                                    <x-level :level="$issue->level" />
                                    <span class="truncate text-[13px] font-medium text-ink-100 hover:text-brand-300">{{ $issue->title }}</span>
                                    @if ($issue->is_regressed) <span class="badge border-amber-500/30 bg-amber-500/10 text-amber-300">regressed</span> @endif
                                </a>
                                <div class="mt-0.5 truncate font-mono text-[11px] text-ink-500">{{ $issue->culprit }}</div>
                            </div>
                            <div wire:ignore x-data="lxSparkline(@js($sparklines[$issue->id] ?? []), '{{ $issue->type === 'error' ? '#f43f5e' : '#38bdf8' }}')" class="hidden sm:block"><canvas x-ref="canvas" width="96" height="28"></canvas></div>
                            <div class="w-16 text-right text-[13px] text-ink-200">{{ number_format($issue->events_count) }}</div>
                            <div class="w-16 text-right text-[11px] text-ink-500"><x-time :value="$issue->last_seen" /></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="card xl:col-span-2">
            <div class="card-header"><span class="card-title">{{ $vitals ? 'Web vitals (p75)' : 'Health' }}</span></div>
            @if ($vitals)
                <div class="grid grid-cols-2 gap-px bg-ink-800">
                    @foreach ([['LCP', $vitals['lcp'], 'ms', 2500, 4000], ['FCP', $vitals['fcp'], 'ms', 1800, 3000], ['TTFB', $vitals['ttfb'], 'ms', 800, 1800], ['CLS', $vitals['cls'], '', 0.1, 0.25]] as [$k, $v, $u, $good, $poor])
                        @php $v = (float) $v; $tone = $v <= $good ? 'text-emerald-300' : ($v <= $poor ? 'text-amber-300' : 'text-red-300'); @endphp
                        <div class="bg-ink-900 px-4 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">{{ $k }}</div>
                            <div class="text-lg font-semibold {{ $tone }}">{{ $u === 'ms' ? number_format($v) : number_format($v, 3) }}<span class="ml-0.5 text-xs text-ink-500">{{ $u }}</span></div>
                        </div>
                    @endforeach
                </div>
                <div class="px-4 py-2 text-[11px] text-ink-500">{{ number_format($vitals['pageloads']) }} page loads in range</div>
            @else
                <dl class="divide-y divide-ink-800 text-[13px]">
                    <div class="flex justify-between px-4 py-2.5"><dt class="text-ink-400">Failed transactions (5xx)</dt><dd class="{{ $summary['failed'] ? 'text-red-300' : 'text-ink-200' }}">{{ number_format($summary['failed']) }}</dd></div>
                    <div class="flex justify-between px-4 py-2.5"><dt class="text-ink-400">p50 latency</dt><dd class="text-ink-200">{{ $summary['p50'] ? number_format($summary['p50']).' ms' : '–' }}</dd></div>
                    <div class="flex justify-between px-4 py-2.5"><dt class="text-ink-400">Distinct issues in range</dt><dd class="text-ink-200">{{ number_format($summary['issues']) }}</dd></div>
                    <div class="flex justify-between px-4 py-2.5"><dt class="text-ink-400">Retention</dt><dd class="text-ink-200">{{ $project->retention_days }} days</dd></div>
                    <div class="flex justify-between px-4 py-2.5"><dt class="text-ink-400">Alert rules</dt><dd class="text-ink-200">{{ $project->alertRules()->where('is_active', true)->count() }} active</dd></div>
                </dl>
            @endif
        </div>
    </div>
</div>
