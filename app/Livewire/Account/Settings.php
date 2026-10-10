<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Settings extends Component
{
    public string $name = '';

    public string $email = '';

    public string $timezone = 'UTC';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->timezone = $user->timezone ?: 'UTC';
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'timezone' => ['required', 'timezone:all'],
        ]);
        $emailChanged = strcasecmp($user->email, $this->email) !== 0;
        $user->fill(['name' => $this->name, 'email' => $this->email, 'timezone' => $this->timezone]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            $this->redirectRoute('verification.notice');

            return;
        }
        session()->flash('status', 'Profile saved.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        if (! Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'Current password is incorrect.');

            return;
        }
        auth()->user()->forceFill(['password' => $this->password])->save();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        session()->flash('status', 'Password updated.');
    }

    public function render()
    {
        return view('livewire.account.settings', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'organizations' => auth()->user()->organizations()->orderBy('name')->get(),
        ])->layoutData(['title' => 'Account']);
    }
}
