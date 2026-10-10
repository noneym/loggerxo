<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Verify your e-mail')]
class VerifyEmail extends Component
{
    public ?string $sent = null;

    public function mount()
    {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('app.dashboard');
        }
    }

    public function resend(): void
    {
        $user = auth()->user();
        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute('app.dashboard');

            return;
        }
        $user->sendEmailVerificationNotification();
        $this->sent = 'A fresh verification link was sent to '.$user->email.'.';
    }

    public function render()
    {
        return view('livewire.auth.verify-email');
    }
}
