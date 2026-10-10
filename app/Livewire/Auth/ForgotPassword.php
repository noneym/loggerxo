<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Reset password')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $sent = null;

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        Password::sendResetLink(['email' => $this->email]);

        // Always show the same message so e-mail addresses cannot be enumerated.
        $this->sent = 'If an account exists for '.$this->email.', a reset link is on its way.';
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
