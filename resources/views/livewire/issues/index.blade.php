<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-1 rounded-lg border border-ink-700 bg-ink-900 p-1">
            @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'ignored' => 'Ignored', 'all' => 'All'] as $key => $label)
                <button wire:click="$set('status', '{{ $key }}')" class="rounded-md px-3 py-1.5 text-[13px] font-medium transition {{ $status === $key ? 'bg-ink-700 text-white' : 'text-ink-400 hover:text-ink-100' }}">
                    {{ $label }}
                    @if ($key !== 'all') <span class="ml-1 text-[11px] text-ink-500">{{ number_format($counts[$key] ?? 0) }}</span> @endif
                </button>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-ink-500" />
                <input type="search" wire:model.live.debounce.400ms="q" placeholder="Search title, file, ID…" class="input w-56 py-1.5 pl-8 text-xs">
            </div>
            <select wire:model.live="type" class="select w-auto py-1.5 text-xs"><option value="">All types</option><option value="error">Errors</option><option value="log">Logs</option><option value="console">Console</option></select>
            <select wire:model.live="level" class="select w-auto py-1.5 text-xs"><option value="">All levels</option>@foreach (\App\Support\Levels::all() as $l)<option value="{{ $l }}">{{ ucfirst($l) }}</option>@endforeach</select>
            @if ($environments->isNotEmpty())
                <select wire:model.live="environment" class="select w-auto py-1.5 text-xs"><option value="">All environments</option>@foreach ($environments as $env)<option value="{{ $env }}">{{ $env }}</option>@endforeach</select>
            @endif
            <select wire:model.live="sort" class="select w-auto py-1.5 text-xs"><option value="last_seen">Last seen</option><option value="first_seen">First seen</option><option value="events">Most events</option><option value="level">Level</option></select>
            <x-range-select wire:model.live="range" title="Sparkline range" />
        </div>
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-[13px] text-amber-200">Sparklines unavailable: {{ $error }}</div>
    @endif

    <div class="card">
        <div class="flex items-center gap-3 border-b border-ink-800 px-4 py-2">
            <input type="checkbox" wire:model.live="selectAll" class="h-4 w-4 rounded border-ink-600 bg-ink-850 text-brand-600">
            @if (count($selected))
                <span class="text-xs text-ink-300">{{ count($selected) }} selected</span>
                <button wire:click="bulk('resolve')" class="btn-secondary btn-sm"><x-icon name="check" class="h-3.5 w-3.5" /> Resolve</button>
                <button wire:click="bulk('ignore')" class="btn-secondary btn-sm"><x-icon name="eye-off" class="h-3.5 w-3.5" /> Ignore</button>
                <button wire:click="bulk('reopen')" class="btn-secondary btn-sm"><x-icon name="refresh" class="h-3.5 w-3.5" /> Reopen</button>
                @if (auth()->user()->canManageProject($project))
                    <button wire:click="bulk('delete')" wire:confirm="Delete the selected issues? Events stay in ClickHouse until retention expires." class="btn-danger btn-sm"><x-icon name="trash" class="h-3.5 w-3.5" /> Delete</button>
                @endif
            @else
                <span class="text-xs text-ink-500">{{ number_format($issues->total()) }} {{ Str::plural('issue', $issues->total()) }}</span>
                <span class="ml-auto hidden text-[11px] text-ink-500 md:block">Events · sparkline shows the selected range</span>
            @endif
        </div>

        @if ($issues->isEmpty())
            <x-empty icon="check" title="Nothing here" body="{{ $status === 'open' ? 'No open issues match your filters.' : 'No issues match your filters.' }}" />
        @else
            <ul class="divide-y divide-ink-800">
                @foreach ($issues as $issue)
                    <li class="flex items-center gap-3 px-4 py-3 hover:bg-ink-850/60" wire:key="issue-{{ $issue->id }}">
                        <input type="checkbox" wire:model.live="selected" value="{{ $issue->id }}" class="h-4 w-4 rounded border-ink-600 bg-ink-850 text-brand-600">
                        <div class="min-w-0 flex-1">
                            <a href="{{ $issue->url() }}" class="group flex items-center gap-2">
                                <x-level :level="$issue->level" />
                                <span class="truncate text-[13px] font-medium text-ink-100 group-hover:text-brand-300">{{ $issue->title }}</span>
                                @if ($issue->is_regressed) <span class="badge border-amber-500/30 bg-amber-500/10 text-amber-300">regressed</span> @endif
                                @if ($issue->status !== 'open') <span class="badge border-ink-600 bg-ink-800 text-ink-400">{{ $issue->status }}</span> @endif
                            </a>
                            <div class="mt-0.5 flex items-center gap-2 text-[11px] text-ink-500">
                                <x-type-badge :type="$issue->type" class="normal-case" />
                                <span class="truncate font-mono">{{ $issue->culprit ?: $issue->shortId() }}</span>
                                @foreach (array_slice($issue->environments ?? [], 0, 3) as $env)
                                    <span class="rounded bg-ink-800 px-1.5 py-0.5">{{ $env }}</span>
                                @endforeach
                                @if ($issue->assignee) <span class="rounded bg-brand-500/10 px-1.5 py-0.5 text-brand-300">{{ $issue->assignee->name }}</span> @endif
                            </div>
                        </div>
                        <div wire:ignore x-data="lxSparkline(@js($sparklines[$issue->id] ?? []), '{{ $issue->type === 'error' ? '#f43f5e' : ($issue->type === 'console' ? '#a78bfa' : '#38bdf8') }}')" class="hidden md:block"><canvas x-ref="canvas" width="110" height="28"></canvas></div>
                        <div class="w-20 text-right">
                            <div class="text-[13px] font-medium text-ink-100">{{ number_format($issue->events_count) }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-ink-500">events</div>
                        </div>
                        <div class="hidden w-24 text-right text-[11px] text-ink-400 sm:block">
                            <div><x-time :value="$issue->last_seen" /></div>
                            <div class="text-ink-600">first <x-time :value="$issue->first_seen" /></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-ink-800 px-4 py-3">{{ $issues->links() }}</div>
        @endif
    </div>
</div>
