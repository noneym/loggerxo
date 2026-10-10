<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrganizationController;
use App\Livewire\Account\Settings as AccountSettings;
use App\Livewire\Admin\Index as AdminIndex;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Dashboard;
use App\Livewire\Issues\Index as IssuesIndex;
use App\Livewire\Issues\Show as IssuesShow;
use App\Livewire\Logs\Explorer as LogsExplorer;
use App\Livewire\Onboarding;
use App\Livewire\Organization\Settings as OrganizationSettings;
use App\Livewire\Performance\Index as PerformanceIndex;
use App\Livewire\Performance\Show as PerformanceShow;
use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Overview as ProjectsOverview;
use App\Livewire\Projects\Settings as ProjectsSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('app.dashboard') : view('marketing.landing'))->name('home');
Route::view('/docs', 'marketing.docs')->name('docs');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::get('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', VerifyEmail::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', Onboarding::class)->name('onboarding');
    Route::get('/organizations/create', Onboarding::class)->name('organizations.create');
    Route::get('/organizations/switch/{organization}', [OrganizationController::class, 'switch'])->name('organization.switch');
    Route::get('/account', AccountSettings::class)->name('account.settings');
});

Route::middleware(['auth', 'verified', 'org'])->prefix('app')->group(function () {
    Route::get('/', Dashboard::class)->name('app.dashboard');
    Route::get('/projects/create', ProjectsCreate::class)->name('projects.create');
    Route::get('/projects/{project}', ProjectsOverview::class)->name('projects.show');
    Route::get('/projects/{project}/issues', IssuesIndex::class)->name('issues.index');
    Route::get('/projects/{project}/issues/{issue}', IssuesShow::class)->name('issues.show');
    Route::get('/projects/{project}/logs', LogsExplorer::class)->name('logs.index');
    Route::get('/projects/{project}/performance', PerformanceIndex::class)->name('performance.index');
    Route::get('/projects/{project}/performance/transaction', PerformanceShow::class)->name('performance.show');
    Route::get('/projects/{project}/settings/{tab?}', ProjectsSettings::class)->name('projects.settings');
    Route::get('/organization/{tab?}', OrganizationSettings::class)->name('organization.settings');
});

Route::middleware(['auth', 'verified', 'superadmin'])->prefix('admin')->group(function () {
    Route::get('/', AdminIndex::class)->name('admin.index');
});
