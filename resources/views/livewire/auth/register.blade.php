<div class="card p-7">
    <h1 class="text-xl font-semibold tracking-tight text-white">Create your account</h1>
    <p class="mt-1 text-[13px] text-ink-400">Errors, logs and performance for all your apps in one place.</p>

    <form wire:submit="register" class="mt-6 space-y-4">
        <div>
            <label class="label" for="name">Name</label>
            <input id="name" type="text" wire:model="name" class="input" autocomplete="name" autofocus required>
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="email" required>
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password" required>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Create account</span>
            <span wire:loading>Creating…</span>
        </button>
    </form>

    <p class="mt-6 text-center text-[13px] text-ink-400">Already registered? <a href="{{ route('login') }}" class="font-medium text-brand-400 hover:text-brand-300">Sign in</a></p>
</div>
