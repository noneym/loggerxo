<div>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-white">{{ $org->name }}</h1>
            <p class="text-[13px] text-ink-400">{{ $projects->count() }} {{ Str::plural('project', $projects->count()) }} · all numbers for the selected range</p>
        </div>
        <div class="flex items-center gap-2">
            <x-range-select wire:model.live="range" />
            <a href="{{ route('projects.create') }}" class="btn-primary"><x-icon name="plus" /> New project</a>
        </div>
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-[13px] text-red-200">ClickHouse query failed: {{ $error }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Errors" :value="$totals['errors']" tone="red" icon="bug" />
        <x-stat label="Events" :value="$totals['events']" tone="sky" icon="layers" />
        <x-stat label="Transactions" :value="$totals['transactions']" tone="emerald" icon="gauge" />
        <x-stat label="Open issues" :value="$totals['open']" tone="amber" icon="inbox" />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><span class="card-title">Events across all projects</span></div>
            <div class="h-[260px] p-4" wire:ignore x-data="lxChart(@js($chart))" data-chart="{{ json_encode($chart) }}" wire:key="org-chart-{{ $chartVersion }}">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">Latest open issues</span></div>
            <ul class="divide-y divide-ink-800">
                @forelse ($recentIssues as $issue)
                    <li>
                        <a href="{{ $issue->url() }}" class="block px-4 py-3 hover:bg-ink-850/60">
                            <div class="flex items-center gap-2">
                                <x-level :level="$issue->level" />
                                <span class="truncate text-[13px] font-medium text-ink-100">{{ $issue->title }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-ink-500">
                                <span class="truncate">{{ $issue->project->name }}</span> ·
                                <x-time :value="$issue->last_seen" /> · {{ number_format($issue->events_count) }} events
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-[13px] text-ink-500">No open issues. Enjoy the silence.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><span class="card-title">Projects</span></div>
        @if ($projects->isEmpty())
            <x-empty icon="folder" title="No projects yet" body="Create a project to get a key and start sending errors and logs.">
                <a href="{{ route('projects.create') }}" class="btn-primary">Create project</a>
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Project</th><th>Platform</th><th class="text-right">Errors</th><th class="text-right">Events</th><th class="text-right">Transactions</th><th class="text-right">p95</th><th class="text-right">Open issues</th><th>Last event</th></tr></thead>
                    <tbody>
                    @foreach ($projects as $p)
                        @php $s = $summary[$p->id] ?? ['errors' => 0, 'events' => 0, 'transactions' => 0, 'p95' => 0]; @endphp
                        <tr>
                            <td><a href="{{ route('projects.show', $p) }}" class="font-medium text-ink-100 hover:text-brand-300">{{ $p->name }}</a></td>
                            <td class="text-ink-400">{{ $p->platformLabel() }}</td>
                            <td class="text-right {{ $s['errors'] ? 'text-red-300' : 'text-ink-400' }}">{{ number_format($s['errors']) }}</td>
                            <td class="text-right text-ink-200">{{ number_format($s['events']) }}</td>
                            <td class="text-right text-ink-200">{{ number_format($s['transactions']) }}</td>
                            <td class="text-right text-ink-200">{{ $s['p95'] ? number_format($s['p95']).' ms' : '–' }}</td>
                            <td class="text-right">{{ $p->open_issues_count }}</td>
                            <td class="text-ink-400">@if ($p->last_event_at)<x-time :value="$p->last_event_at" />@else <span class="text-ink-600">never</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
