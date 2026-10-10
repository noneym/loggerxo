<div class="card p-7">
    <h1 class="text-xl font-semibold tracking-tight text-white">Welcome back</h1>
    <p class="mt-1 text-[13px] text-ink-400">Sign in to your Loggerxo account.</p>

    <form wire:submit="login" class="mt-6 space-y-4">
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="email" autofocus required>
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label class="label" for="password">Password</label>
                <a href="{{ route('password.request') }}" class="mb-1.5 text-xs text-brand-400 hover:text-brand-300">Forgot?</a>
            </div>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="current-password" required>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-[13px] text-ink-300">
            <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-ink-600 bg-ink-850 text-brand-600 focus:ring-brand-500/40">
            Keep me signed in
        </label>
        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in…</span>
        </button>
    </form>

    @if (config('loggerxo.registration_enabled'))
        <p class="mt-6 text-center text-[13px] text-ink-400">No account yet? <a href="{{ route('register') }}" class="font-medium text-brand-400 hover:text-brand-300">Create one</a></p>
    @endif
</div>
