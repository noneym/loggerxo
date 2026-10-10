<div wire:poll.15s>
    <h1 class="mb-5 text-xl font-semibold tracking-tight text-white">Platform admin</h1>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        <x-stat label="Users" :value="$counts['users']" tone="sky" icon="users" />
        <x-stat label="Organizations" :value="$counts['organizations']" tone="violet" icon="folder" />
        <x-stat label="Projects" :value="$counts['projects']" tone="emerald" icon="layers" />
        <x-stat label="Events 24h" :value="$stats['events_24h'] ?? '–'" tone="amber" icon="list" />
        <x-stat label="Transactions 24h" :value="$stats['transactions_24h'] ?? '–'" tone="emerald" icon="gauge" />
        <x-stat label="Ingest backlog" :value="$queueSize ?? '–'" :tone="($queueSize ?? 0) > 1000 ? 'red' : 'zinc'" icon="inbox" />
        <x-stat label="Dead letters" :value="$deadSize ?? '–'" :tone="($deadSize ?? 0) > 0 ? 'red' : 'zinc'" icon="warning" />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card p-4 text-[13px]">
            <div class="card-title mb-3">Services</div>
            <ul class="space-y-2">
                <li class="flex items-center justify-between"><span class="text-ink-300">ClickHouse</span><span class="badge {{ $chOk ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-red-500/30 bg-red-500/10 text-red-300' }}">{{ $chOk ? 'connected' : 'down' }}</span></li>
                <li class="flex items-center justify-between"><span class="text-ink-300">Redis</span><span class="badge {{ $queueSize !== null ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-red-500/30 bg-red-500/10 text-red-300' }}">{{ $queueSize !== null ? 'connected' : 'down' }}</span></li>
                <li class="flex items-center justify-between"><span class="text-ink-300">Mail driver</span><span class="text-ink-200">{{ config('mail.default') }}</span></li>
                <li class="flex items-center justify-between"><span class="text-ink-300">Registration</span><span class="text-ink-200">{{ config('loggerxo.registration_enabled') ? 'open' : 'closed' }}</span></li>
            </ul>
            @foreach ($stats['disk'] ?? [] as $d)
                <div class="mt-3 flex items-center justify-between border-t border-ink-800 pt-2 text-[12px]"><span class="font-mono text-ink-400">{{ $d['table'] }}</span><span class="text-ink-200">{{ number_format($d['rows']) }} rows · {{ $d['size'] }}</span></div>
            @endforeach
            @if (($deadSize ?? 0) > 0)
                <button wire:click="requeueDead" class="btn-secondary btn-sm mt-4 w-full">Requeue {{ $deadSize }} dead letters</button>
            @endif
        </div>

        <div class="card xl:col-span-2">
            <div class="card-header"><span class="card-title">Organizations</span></div>
            <div class="overflow-x-auto"><table class="table"><thead><tr><th>Name</th><th>Owner</th><th class="text-right">Projects</th><th class="text-right">Members</th><th>Created</th><th></th></tr></thead><tbody>
                @foreach ($organizations as $o)
                    <tr><td class="text-ink-100">{{ $o->name }}</td><td class="text-ink-400">{{ $o->owner?->email }}</td><td class="text-right">{{ $o->projects_count }}</td><td class="text-right">{{ $o->users_count }}</td><td class="text-ink-400"><x-time :value="$o->created_at" /></td><td class="text-right"><a href="{{ route('organization.switch', $o) }}" class="text-xs text-brand-400 hover:text-brand-300">Open</a></td></tr>
                @endforeach
            </tbody></table></div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><span class="card-title">Users</span><span class="text-xs text-ink-500">latest 50</span></div>
        <div class="overflow-x-auto"><table class="table"><thead><tr><th>Name</th><th>E-mail</th><th>Verified</th><th class="text-right">Orgs</th><th>Last login</th><th>Super admin</th></tr></thead><tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="text-ink-100">{{ $u->name }}</td><td class="text-ink-400">{{ $u->email }}</td>
                    <td>{!! $u->email_verified_at ? '<span class="text-emerald-300">yes</span>' : '<span class="text-amber-300">no</span>' !!}</td>
                    <td class="text-right">{{ $u->organizations_count }}</td>
                    <td class="text-ink-400">@if ($u->last_login_at)<x-time :value="$u->last_login_at" />@else –@endif</td>
                    <td><button wire:click="toggleSuperAdmin({{ $u->id }})" class="badge {{ $u->is_super_admin ? 'border-brand-500/40 bg-brand-500/15 text-brand-200' : 'border-ink-600 bg-ink-800 text-ink-400' }}" @disabled($u->id === auth()->id())>{{ $u->is_super_admin ? 'super admin' : 'user' }}</button></td>
                </tr>
            @endforeach
        </tbody></table></div>
    </div>
</div>
