<div class="card p-7">
    <h1 class="text-xl font-semibold tracking-tight text-white">Choose a new password</h1>

    <form wire:submit="resetPassword" class="mt-6 space-y-4">
        <div>
            <label class="label" for="email">E-mail</label>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="email" required>
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password">New password</label>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password" autofocus required>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Update password</button>
    </form>
</div>
