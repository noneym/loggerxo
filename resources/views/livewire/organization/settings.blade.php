<div>
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight text-white">{{ $organization->name }}</h1>
        <p class="text-[13px] text-ink-400">Members, invitations and organization settings.</p>
    </div>

    <div class="mb-5 flex items-center gap-1 border-b border-ink-800">
        @foreach (['members' => 'Members', 'general' => 'General', 'danger' => 'Danger zone'] as $k => $l)
            <button wire:click="$set('tab', '{{ $k }}')" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $l }}</button>
        @endforeach
    </div>

    @if ($tab === 'members')
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="card xl:col-span-2">
                <div class="card-header"><span class="card-title">Members</span><span class="text-xs text-ink-500">{{ $members->count() }}</span></div>
                <ul class="divide-y divide-ink-800">
                    @foreach ($members as $m)
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-ink-700 text-[11px] font-semibold text-ink-100">{{ $m->initials() }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13px] font-medium text-ink-100">{{ $m->name }} @if ($m->id === auth()->id())<span class="text-ink-500">(you)</span>@endif</div>
                                <div class="truncate text-[11px] text-ink-500">{{ $m->email }}</div>
                            </div>
                            @if ($canManage)
                                <select wire:change="changeRole({{ $m->id }}, $event.target.value)" class="select w-auto py-1 text-xs">
                                    <option value="owner" @selected($m->pivot->role === 'owner') @disabled(! $isOwner)>Owner</option>
                                    <option value="admin" @selected($m->pivot->role === 'admin')>Admin</option>
                                    <option value="member" @selected($m->pivot->role === 'member')>Member</option>
                                </select>
                                <button wire:click="remove({{ $m->id }})" wire:confirm="Remove {{ $m->name }} from the organization?" class="btn-ghost btn-sm text-red-300"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                            @else
                                <span class="badge border-ink-600 bg-ink-800 text-ink-300">{{ $m->pivot->role }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($invitations->isNotEmpty())
                    <div class="border-t border-ink-800 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Pending invitations</div>
                    <ul class="divide-y divide-ink-800">
                        @foreach ($invitations as $inv)
                            <li class="flex items-center gap-3 px-4 py-2.5 text-[13px]">
                                <x-icon name="mail" class="text-ink-500" />
                                <div class="min-w-0 flex-1"><div class="text-ink-200">{{ $inv->email }}</div><div class="text-[11px] text-ink-500">{{ $inv->role }} · expires <x-time :value="$inv->expires_at" /></div></div>
                                @if ($canManage)
                                    <button onclick="lxCopy('{{ $inv->acceptUrl() }}', this)" class="btn-ghost btn-sm">Copy link</button>
                                    <button wire:click="revoke({{ $inv->id }})" class="btn-ghost btn-sm text-red-300">Revoke</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @if ($canManage)
                <form wire:submit="invite" class="card self-start space-y-3 p-5">
                    <div class="card-title">Invite a teammate</div>
                    <div><label class="label">E-mail</label><input type="email" wire:model="inviteEmail" class="input" placeholder="colleague@example.com">@error('inviteEmail')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">Role</label><select wire:model="inviteRole" class="select"><option value="member">Member – view projects, manage issues</option><option value="admin">Admin – also manage projects, alerts and members</option></select></div>
                    <button type="submit" class="btn-primary w-full">Send invitation</button>
                    <p class="hint">The link is valid for 7 days. If e-mail delivery fails you can copy the link from the pending list.</p>
                </form>
            @endif
        </div>
    @elseif ($tab === 'general')
        <form wire:submit="saveGeneral" class="card max-w-xl space-y-4 p-6">
            <div><label class="label">Organization name</label><input type="text" wire:model="name" class="input" @disabled(! $canManage)>@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Slug</label><input type="text" value="{{ $organization->slug }}" class="input" disabled></div>
            @if ($canManage)<div class="flex justify-end border-t border-ink-800 pt-4"><button type="submit" class="btn-primary">Save</button></div>@endif
        </form>
    @else
        <div class="card max-w-xl border-red-500/30 p-6">
            <div class="card-title text-red-300">Delete organization</div>
            <p class="mt-2 text-[13px] text-ink-300">Deletes every project, issue and alert rule in this organization. Only owners can do this.</p>
            <button wire:click="deleteOrganization" wire:confirm="Delete {{ $organization->name }} and all of its projects?" class="btn-danger mt-4" @disabled(! $isOwner)><x-icon name="trash" /> Delete organization</button>
        </div>
    @endif
</div>
