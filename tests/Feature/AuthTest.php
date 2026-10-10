<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_user_and_sends_verification(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->set('name', 'Jane')
            ->set('email', 'jane@example.com')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        Notification::assertSentTo(User::first(), \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'nope')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_unverified_users_are_sent_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/app')->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_without_organization_goes_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app')->assertRedirect(route('onboarding'));
    }
}
