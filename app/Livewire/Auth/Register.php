<?php

namespace App\Livewire\Auth;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Create account')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! config('loggerxo.registration_enabled') && ! session('pending_invitation')) {
            abort(403, 'Registration is disabled. Ask an administrator for an invitation.');
        }
        $this->email = (string) session('invitation_email', '');
    }

    public function register()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data);
        AuthController::promoteSuperAdmin($user);
        event(new Registered($user));
        Auth::login($user, true);
        session()->regenerate();
        InvitationController::acceptPending(request());

        return redirect()->route('verification.notice');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
