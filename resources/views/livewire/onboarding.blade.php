<div class="card p-7">
    <h1 class="text-xl font-semibold tracking-tight text-white">{{ $hasOrganizations ? 'New organization' : 'Name your organization' }}</h1>
    <p class="mt-1 text-[13px] text-ink-400">Organizations group your projects and team members. You can rename it later.</p>

    <form wire:submit="create" class="mt-6 space-y-4">
        <div>
            <label class="label" for="name">Organization name</label>
            <input id="name" type="text" wire:model="name" class="input" autofocus required placeholder="Acme Inc.">
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Continue</button>
    </form>

    @if ($hasOrganizations)
        <p class="mt-6 text-center text-[13px] text-ink-400"><a href="{{ route('app.dashboard') }}" class="font-medium text-brand-400 hover:text-brand-300">Back to dashboard</a></p>
    @endif
</div>
