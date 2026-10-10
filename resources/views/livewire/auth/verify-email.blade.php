<div class="card p-7">
    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600/15 text-brand-300">
        <x-icon name="mail" class="h-5 w-5" />
    </div>
    <h1 class="mt-4 text-xl font-semibold tracking-tight text-white">Check your inbox</h1>
    <p class="mt-1 text-[13px] text-ink-400">We sent a verification link to <span class="font-medium text-ink-200">{{ auth()->user()->email }}</span>. Click it to activate your account.</p>

    @if ($sent)
        <div class="mt-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-[13px] text-emerald-200">{{ $sent }}</div>
    @endif

    <div class="mt-6 flex items-center gap-3">
        <button wire:click="resend" class="btn-secondary" wire:loading.attr="disabled">Resend e-mail</button>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-ghost">Sign out</button>
        </form>
    </div>
</div>
