<div class="mx-auto max-w-xl">
    <h1 class="text-xl font-semibold tracking-tight text-white">Create a project</h1>
    <p class="mt-1 text-[13px] text-ink-400">One project per application. Each project gets its own key, issues, logs and alert rules.</p>

    <form wire:submit="create" class="card mt-6 space-y-5 p-6">
        <div>
            <label class="label" for="name">Project name</label>
            <input id="name" type="text" wire:model="name" class="input" placeholder="Shop API" autofocus required>
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Platform</label>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($platforms as $key => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-[13px] transition {{ $platform === $key ? 'border-brand-500 bg-brand-500/10 text-white' : 'border-ink-700 bg-ink-850 text-ink-300 hover:border-ink-500' }}">
                        <input type="radio" wire:model.live="platform" value="{{ $key }}" class="sr-only">
                        <x-icon :name="$key === 'browser' ? 'globe' : ($key === 'other' ? 'code' : 'terminal')" class="h-4 w-4" />
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="hint">Only changes which setup instructions you see. Every project accepts server and browser events.</p>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-ink-800 pt-4">
            <a href="{{ route('app.dashboard') }}" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">Create project</button>
        </div>
    </form>
</div>
