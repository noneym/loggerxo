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

    <div class="card p-6 lg:col-span-2">
        <div class="card-title">Your organizations</div>
        <ul class="mt-3 divide-y divide-ink-800">
            @foreach ($organizations as $o)
                <li class="flex items-center justify-between py-2.5 text-[13px]"><span class="text-ink-100">{{ $o->name }}</span><span class="flex items-center gap-3"><span class="badge border-ink-600 bg-ink-800 text-ink-300">{{ $o->pivot->role }}</span><a href="{{ route('organization.switch', $o) }}" class="text-brand-400 hover:text-brand-300">Switch</a></span></li>
            @endforeach
        </ul>
    </div>
</div>
