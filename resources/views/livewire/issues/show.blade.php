<div>
    {{-- Header --}}
    <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <a href="{{ route('issues.index', $project) }}" class="inline-flex items-center gap-1 text-xs text-ink-400 hover:text-ink-100"><x-icon name="chevron-left" class="h-3.5 w-3.5" /> Issues</a>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <x-level :level="$issue->level" />
                <x-type-badge :type="$issue->type" />
                @if ($issue->status !== 'open') <span class="badge border-ink-600 bg-ink-800 text-ink-300">{{ $issue->status }}</span> @endif
                @if ($issue->is_regressed) <span class="badge border-amber-500/30 bg-amber-500/10 text-amber-300">regressed</span> @endif
                <span class="font-mono text-[11px] text-ink-500">{{ $issue->shortId() }}</span>
            </div>
            <h1 class="mt-1.5 break-words text-lg font-semibold leading-snug text-white">{{ $issue->title }}</h1>
            @if ($issue->culprit) <div class="mt-1 break-all font-mono text-[12px] text-ink-400">{{ $issue->culprit }}</div> @endif
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2" x-data="{ ignore: false, assign: false }">
            @if ($issue->status === 'resolved' || $issue->status === 'ignored')
                <button wire:click="reopen" class="btn-secondary"><x-icon name="refresh" /> Reopen</button>
            @else
                <button wire:click="resolve" class="btn-primary"><x-icon name="check" /> Resolve</button>
                <div class="relative" @click.outside="ignore = false">
                    <button @click="ignore = !ignore" class="btn-secondary"><x-icon name="eye-off" /> Ignore <x-icon name="chevron-down" class="h-3 w-3" /></button>
                    <div x-cloak x-show="ignore" class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-ink-700 bg-ink-850 p-1 shadow-2xl">
                        @foreach (['1h' => 'For 1 hour', '24h' => 'For 24 hours', '7d' => 'For 7 days', 'forever' => 'Until reopened'] as $k => $l)
                            <button wire:click="ignore('{{ $k }}')" @click="ignore = false" class="block w-full rounded-md px-2.5 py-1.5 text-left text-[13px] text-ink-300 hover:bg-ink-800">{{ $l }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="relative" @click.outside="assign = false">
                <button @click="assign = !assign" class="btn-secondary"><x-icon name="user" /> {{ $issue->assignee?->name ?? 'Assign' }} <x-icon name="chevron-down" class="h-3 w-3" /></button>
                <div x-cloak x-show="assign" class="absolute right-0 z-20 mt-1 w-52 rounded-lg border border-ink-700 bg-ink-850 p-1 shadow-2xl">
                    @foreach ($members as $m)
                        <button wire:click="assign({{ $m->id }})" @click="assign = false" class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-[13px] text-ink-300 hover:bg-ink-800">{{ $m->name }} @if ($issue->assigned_to_id === $m->id)<x-icon name="check" class="h-3.5 w-3.5 text-brand-400" />@endif</button>
                    @endforeach
                    @if ($issue->assigned_to_id)
                        <div class="my-1 border-t border-ink-700"></div>
                        <button wire:click="assign(null)" @click="assign = false" class="block w-full rounded-md px-2.5 py-1.5 text-left text-[13px] text-ink-400 hover:bg-ink-800">Unassign</button>
                    @endif
                </div>
            </div>
            @if (auth()->user()->canManageProject($project))
                <button wire:click="delete" wire:confirm="Delete this issue? It will be recreated if the error happens again." class="btn-ghost text-red-300"><x-icon name="trash" /></button>
            @endif
        </div>
    </div>

    @if ($error)
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-[13px] text-red-200">ClickHouse query failed: {{ $error }}</div>
    @endif

    {{-- Stats + chart --}}
    <div class="grid gap-4 xl:grid-cols-4">
        <div class="card xl:col-span-3">
            <div class="card-header"><span class="card-title">Events over time</span><x-range-select wire:model.live="range" /></div>
            <div class="h-[150px] p-3" wire:ignore x-data="lxChart(@js($chart))" wire:key="issue-chart-{{ $chartVersion }}"><canvas x-ref="canvas"></canvas></div>
        </div>
        <div class="card grid grid-cols-2 gap-px overflow-hidden bg-ink-800 xl:grid-cols-1">
            <div class="bg-ink-900 px-4 py-3"><div class="text-[11px] uppercase tracking-wider text-ink-500">Events</div><div class="text-lg font-semibold text-white">{{ number_format($issue->events_count) }}</div></div>
            <div class="bg-ink-900 px-4 py-3"><div class="text-[11px] uppercase tracking-wider text-ink-500">First seen</div><div class="text-[13px] text-ink-200"><x-time :value="$issue->first_seen" /></div><div class="text-[11px] text-ink-500">{{ $issue->first_seen->format('M j, Y H:i') }} UTC</div></div>
            <div class="bg-ink-900 px-4 py-3"><div class="text-[11px] uppercase tracking-wider text-ink-500">Last seen</div><div class="text-[13px] text-ink-200"><x-time :value="$issue->last_seen" /></div><div class="text-[11px] text-ink-500">{{ $issue->last_seen->format('M j, Y H:i') }} UTC</div></div>
            <div class="bg-ink-900 px-4 py-3"><div class="text-[11px] uppercase tracking-wider text-ink-500">Environments</div><div class="flex flex-wrap gap-1 pt-1">@foreach ($issue->environments ?? [] as $env)<span class="rounded bg-ink-800 px-1.5 py-0.5 text-[11px] text-ink-300">{{ $env }}</span>@endforeach</div></div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mt-5 flex items-center gap-1 border-b border-ink-800">
        @foreach (['event' => 'Event details', 'events' => 'All events', 'facets' => 'Breakdown', 'activity' => 'Activity'] as $k => $l)
            <button wire:click="$set('tab', '{{ $k }}')" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $l }}</button>
        @endforeach
    </div>

    <div class="mt-4">
        @if ($tab === 'event')
            @if (! $event)
                <x-empty icon="clock" title="No event data available" body="Events for this issue are older than the retention window or still being written." />
            @else
                @include('livewire.issues.partials.event', ['event' => $event, 'neighbours' => $neighbours])
            @endif
        @elseif ($tab === 'events')
            <div class="card overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Time</th><th>Level</th><th>Message</th><th>Environment</th><th>User</th><th>Browser / OS</th><th>Release</th></tr></thead>
                    <tbody>
                    @forelse ($events['rows'] as $row)
                        <tr class="cursor-pointer" wire:click="showEvent('{{ $row['event_id'] }}')">
                            <td class="whitespace-nowrap text-ink-300"><x-time :value="$row['timestamp']" /></td>
                            <td><x-level :level="$row['level']" /></td>
                            <td class="max-w-md truncate text-ink-100">{{ $row['message'] }}</td>
                            <td class="text-ink-400">{{ $row['environment'] }}</td>
                            <td class="text-ink-400">{{ $row['user_email'] ?: $row['user_id'] ?: '–' }}</td>
                            <td class="text-ink-400">{{ trim($row['browser'].' '.$row['os']) ?: '–' }}</td>
                            <td class="font-mono text-[11px] text-ink-500">{{ $row['release'] ?: '–' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-ink-500">No events in the last 30 days.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if ($events['has_more'])
                    <div class="border-t border-ink-800 p-3 text-center"><button wire:click="loadMoreEvents('{{ $events['next'] }}')" class="btn-secondary btn-sm">Load older events</button></div>
                @endif
            </div>
        @elseif ($tab === 'facets')
            <div class="mb-3 text-[13px] text-ink-400">{{ number_format($facets['users']) }} distinct users affected in the selected range.</div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($facets['facets'] as $column => $rows)
                    @php $total = max(1, array_sum(array_column($rows, 'c'))); @endphp
                    <div class="card">
                        <div class="card-header"><span class="card-title">{{ str_replace('_', ' ', $column) }}</span></div>
                        <ul class="divide-y divide-ink-800">
                            @foreach ($rows as $row)
                                <li class="relative px-4 py-2 text-[13px]">
                                    <div class="absolute inset-y-0 left-0 bg-brand-500/10" style="width: {{ round($row['c'] / $total * 100) }}%"></div>
                                    <div class="relative flex items-center justify-between gap-3"><span class="truncate text-ink-200">{{ $row['v'] }}</span><span class="text-ink-400">{{ number_format($row['c']) }} · {{ round($row['c'] / $total * 100) }}%</span></div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="md:col-span-2 xl:col-span-3"><x-empty icon="layers" title="No breakdown data" body="No events in the selected range." /></div>
                @endforelse
            </div>
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="card xl:col-span-2">
                    <div class="card-header"><span class="card-title">Activity</span></div>
                    <ul class="divide-y divide-ink-800">
                        @forelse ($comments as $c)
                            <li class="flex gap-3 px-4 py-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink-700 text-[11px] font-semibold text-ink-100">{{ $c->user?->initials() ?? '?' }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px]"><span class="font-medium text-ink-100">{{ $c->user?->name ?? 'System' }}</span> @if ($c->kind !== 'comment')<span class="text-ink-400">{{ $c->body }}</span>@endif</div>
                                    @if ($c->kind === 'comment') <div class="mt-1 whitespace-pre-wrap text-[13px] text-ink-200">{{ $c->body }}</div> @endif
                                    <div class="mt-1 text-[11px] text-ink-500"><x-time :value="$c->created_at" /></div>
                                </div>
                            </li>
                        @empty
                            <li class="px-4 py-8 text-center text-[13px] text-ink-500">No activity yet.</li>
                        @endforelse
                    </ul>
                    <form wire:submit="addComment" class="border-t border-ink-800 p-4">
                        <textarea wire:model="comment" rows="3" class="input" placeholder="Leave a note for your team…"></textarea>
                        @error('comment') <p class="error">{{ $message }}</p> @enderror
                        <div class="mt-2 flex justify-end"><button type="submit" class="btn-primary btn-sm">Comment</button></div>
                    </form>
                </div>
                <div class="card p-4 text-[13px]">
                    <div class="card-title mb-3">Details</div>
                    <dl class="space-y-2">
                        <div class="flex justify-between gap-3"><dt class="text-ink-400">Fingerprint</dt><dd class="truncate font-mono text-[11px] text-ink-300">{{ $issue->fingerprint }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-400">Platform</dt><dd class="text-ink-200">{{ $issue->platform }}</dd></div>
                        @foreach ($issue->metadata ?? [] as $k => $v)
                            <div class="flex justify-between gap-3"><dt class="text-ink-400">{{ str_replace('_', ' ', $k) }}</dt><dd class="truncate text-ink-200">{{ is_scalar($v) ? $v : json_encode($v) }}</dd></div>
                        @endforeach
                        @if ($issue->resolved_at)<div class="flex justify-between gap-3"><dt class="text-ink-400">Resolved</dt><dd class="text-ink-200"><x-time :value="$issue->resolved_at" /> by {{ $issue->resolver?->name ?? '–' }}</dd></div>@endif
                        @if ($issue->ignored_until)<div class="flex justify-between gap-3"><dt class="text-ink-400">Ignored until</dt><dd class="text-ink-200">{{ $issue->ignored_until->format('M j H:i') }}</dd></div>@endif
                    </dl>
                </div>
            </div>
        @endif
    </div>
</div>
