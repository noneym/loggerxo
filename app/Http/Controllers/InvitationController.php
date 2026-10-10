<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = OrganizationInvitation::with('organization')->where('token', $token)->first();
        if (! $invitation || ! $invitation->isPending()) {
            return redirect()->route('home')->with('error', 'This invitation link is invalid or has expired.');
        }

        $user = $request->user();
        if (! $user) {
            session(['pending_invitation' => $token, 'invitation_email' => $invitation->email]);

            return redirect()->route('register')->with('status', 'Create an account (or sign in) to join '.$invitation->organization->name.'.');
        }

        if (strcasecmp($user->email, $invitation->email) !== 0) {
            return redirect()->route('app.dashboard')->with('error', 'This invitation was sent to '.$invitation->email.'. Sign in with that address to accept it.');
        }

        $invitation->organization->addMember($user, $invitation->role);
        $invitation->forceFill(['accepted_at' => now()])->save();
        $user->forceFill(['current_organization_id' => $invitation->organization_id])->save();
        session()->forget(['pending_invitation', 'invitation_email']);

        return redirect()->route('app.dashboard')->with('status', 'You joined '.$invitation->organization->name.'.');
    }

    /**
     * Accept any invitation stored in the session (after registration / login).
     */
    public static function acceptPending(Request $request): void
    {
        $token = session('pending_invitation');
        $user = $request->user();
        if (! $token || ! $user) {
            return;
        }
        $invitation = OrganizationInvitation::with('organization')->where('token', $token)->first();
        if ($invitation && $invitation->isPending() && strcasecmp($user->email, $invitation->email) === 0) {
            $invitation->organization->addMember($user, $invitation->role);
            $invitation->forceFill(['accepted_at' => now()])->save();
            $user->forceFill(['current_organization_id' => $invitation->organization_id])->save();
        }
        session()->forget(['pending_invitation', 'invitation_email']);
    }
}
