<div class="grid max-w-4xl gap-4 lg:grid-cols-2">
    <form wire:submit="saveProfile" class="card space-y-4 p-6">
        <div class="card-title">Profile</div>
        <div><label class="label">Name</label><input type="text" wire:model="name" class="input">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label">E-mail</label><input type="email" wire:model="email" class="input"><p class="hint">Changing your e-mail requires verifying the new address.</p>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Timezone</label><select wire:model="timezone" class="select">@foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach</select>@error('timezone')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="flex justify-end border-t border-ink-800 pt-4"><button type="submit" class="btn-primary">Save profile</button></div>
    </form>

    <form wire:submit="savePassword" class="card space-y-4 p-6">
        <div class="card-title">Password</div>
        <div><label class="label">Current password</label><input type="password" wire:model="current_password" class="input" autocomplete="current-password">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label">New password</label><input type="password" wire:model="password" class="input" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Confirm new password</label><input type="password" wire:model="password_confirmation" class="input" autocomplete="new-password"></div>
        <div class="flex justify-end border-t border-ink-800 pt-4"><button type="submit" class="btn-primary">Update password</button></div>
    </form>

    <div class="card space-y-4 p-6 lg:col-span-2" id="api-tokens">
        <div>
            <div class="card-title">API tokens</div>
            <p class="hint">Let an AI assistant read your projects, issues and events over <a href="{{ route('docs') }}#mcp" class="text-brand-400 hover:text-brand-300">MCP</a>. A token sees what you see, read-only.</p>
        </div>

        @if ($new_token)
            <div class="rounded-lg border border-brand-500/40 bg-brand-500/10 p-4">
                <p class="text-[13px] text-ink-100">Copy this token now. It is not shown again.</p>
                <x-code class="mt-2">{{ $new_token }}</x-code>
                <p class="mt-3 text-[13px] text-ink-300">Claude Code:</p>
                <x-code class="mt-1">{{ 'claude mcp add --transport http loggerxo '.$mcpUrl.' --header "Authorization: Bearer '.$new_token.'"' }}</x-code>
            </div>
        @endif

        <form wire:submit="createToken" class="flex items-start gap-2">
            <div class="flex-1"><input type="text" wire:model="token_name" class="input" placeholder="Token name, e.g. Claude Code on my laptop" maxlength="100">@error('token_name')<p class="error">{{ $message }}</p>@enderror</div>
            <button type="submit" class="btn-primary">Create token</button>
        </form>

        @if ($tokens->isNotEmpty())
            <ul class="divide-y divide-ink-800">
                @foreach ($tokens as $t)
                    <li wire:key="token-{{ $t->id }}" class="flex items-center justify-between gap-3 py-2.5 text-[13px]">
                        <span class="min-w-0"><span class="text-ink-100">{{ $t->name }}</span> <span class="font-mono text-ink-500">lxp_…{{ $t->token_hint }}</span></span>
                        <span class="flex shrink-0 items-center gap-3 text-ink-400">
                            <span>{{ $t->last_used_at ? 'Used '.$t->last_used_at->diffForHumans() : 'Never used' }}</span>
                            <button type="button" wire:click="revokeToken({{ $t->id }})" wire:confirm="Revoke this token? Clients using it will stop working." class="text-red-400 hover:text-red-300">Revoke</button>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="hint">Endpoint: <code class="font-mono text-ink-300">{{ $mcpUrl }}</code></p>
    </div>

    <div class="card p-6 lg:col-span-2">
        <div class="card-title">Your organizations</div>
        <ul class="mt-3 divide-y divide-ink-800">
            @foreach ($organizations as $o)
                <li class="flex items-center justify-between py-2.5 text-[13px]"><span class="text-ink-100">{{ $o->name }}</span><span class="flex items-center gap-3"><span class="badge border-ink-600 bg-ink-800 text-ink-300">{{ $o->pivot->role }}</span><a href="{{ route('organization.switch', $o) }}" class="text-brand-400 hover:text-brand-300">Switch</a></span></li>
            @endforeach
        </ul>
    </div>
</div>
