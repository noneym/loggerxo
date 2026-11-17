<?php

namespace App\Livewire\Account;

use App\Models\ApiToken;
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

    public string $token_name = '';

    /** Plain value of the token that was just created; shown once and never stored. */
    public ?string $new_token = null;

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

    public function createToken(): void
    {
        $this->validate(['token_name' => ['required', 'string', 'max:100']]);
        if (auth()->user()->apiTokens()->count() >= 20) {
            $this->addError('token_name', 'You can have at most 20 tokens. Revoke one first.');

            return;
        }
        [, $this->new_token] = ApiToken::issue(auth()->user(), trim($this->token_name));
        $this->reset('token_name');
    }

    public function revokeToken(int $id): void
    {
        auth()->user()->apiTokens()->whereKey($id)->delete();
        $this->new_token = null;
    }

    public function render()
    {
        return view('livewire.account.settings', [
            'tokens' => auth()->user()->apiTokens()->latest()->get(),
            'mcpUrl' => route('mcp'),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'organizations' => auth()->user()->organizations()->orderBy('name')->get(),
        ])->layoutData(['title' => 'Account']);
    }
}
