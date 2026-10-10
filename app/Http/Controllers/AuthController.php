<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = $request->user();
        if ($user->getKey() !== $id) {
            abort(403);
        }
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid verification link.');
        }
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('app.dashboard')->with('status', 'E-mail verified. Welcome!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public static function promoteSuperAdmin(User $user): void
    {
        $emails = array_map('strtolower', config('loggerxo.super_admin_emails', []));
        if ($emails !== [] && in_array(strtolower($user->email), $emails, true) && ! $user->is_super_admin) {
            $user->forceFill(['is_super_admin' => true])->save();
        }
    }
}
