<div class="card p-7">
    <h1 class="text-xl font-semibold tracking-tight text-white">Reset your password</h1>
    <p class="mt-1 text-[13px] text-ink-400">Enter your e-mail and we will send you a link to choose a new password.</p>

    @if ($sent)
        <div class="mt-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-[13px] text-emerald-200">{{ $sent }}</div>
    @else
        <form wire:submit="send" class="mt-6 space-y-4">
            <div>
                <label class="label" for="email">E-mail</label>
                <input id="email" type="email" wire:model="email" class="input" autocomplete="email" autofocus required>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Send reset link</button>
        </form>
    @endif

    <p class="mt-6 text-center text-[13px] text-ink-400"><a href="{{ route('login') }}" class="font-medium text-brand-400 hover:text-brand-300">Back to sign in</a></p>
</div>
