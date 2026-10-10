<?php

namespace App\Livewire\Organization;

use App\Mail\InvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Settings extends Component
{
    #[Locked]
    public Organization $organization;

    public string $tab = 'members';

    public string $name = '';

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public function mount(string $tab = 'members'): void
    {
        $this->organization = auth()->user()->resolveCurrentOrganization();
        abort_unless(auth()->user()->belongsToOrganization($this->organization), 403);
        $this->tab = in_array($tab, ['members', 'general', 'danger'], true) ? $tab : 'members';
        $this->name = $this->organization->name;
    }

    protected function manager(): void
    {
        abort_unless(auth()->user()->canManageOrganization($this->organization), 403);
    }

    public function saveGeneral(): void
    {
        $this->manager();
        $this->validate(['name' => ['required', 'string', 'min:2', 'max:120']]);
        $this->organization->update(['name' => $this->name]);
        session()->flash('status', 'Organization renamed.');
    }

    public function invite(): void
    {
        $this->manager();
        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', 'in:admin,member'],
        ]);
        if ($this->organization->users()->where('email', $this->inviteEmail)->exists()) {
            $this->addError('inviteEmail', 'This person is already a member.');

            return;
        }
        $invitation = OrganizationInvitation::updateOrCreate(
            ['organization_id' => $this->organization->id, 'email' => strtolower($this->inviteEmail), 'accepted_at' => null],
            ['role' => $this->inviteRole, 'token' => Str::random(48), 'invited_by' => auth()->id(), 'expires_at' => now()->addDays(7)]
        );
        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation));
            session()->flash('status', 'Invitation sent to '.$invitation->email.'.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Invitation saved but the e-mail failed: '.$e->getMessage().' Share the link manually.');
        }
        $this->reset(['inviteEmail', 'inviteRole']);
    }

    public function revoke(int $id): void
    {
        $this->manager();
        $this->organization->invitations()->whereKey($id)->delete();
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->manager();
        if (! in_array($role, ['owner', 'admin', 'member'], true)) {
            return;
        }
        if ($role === 'owner' && ! auth()->user()->ownsOrganization($this->organization)) {
            abort(403);
        }
        if ($this->isLastOwner($userId) && $role !== 'owner') {
            session()->flash('error', 'An organization needs at least one owner.');

            return;
        }
        $this->organization->users()->updateExistingPivot($userId, ['role' => $role]);
    }

    public function remove(int $userId): void
    {
        $this->manager();
        if ($this->isLastOwner($userId)) {
            session()->flash('error', 'You cannot remove the last owner.');

            return;
        }
        $this->organization->users()->detach($userId);
        \App\Models\User::whereKey($userId)->where('current_organization_id', $this->organization->id)->update(['current_organization_id' => null]);
        if ($userId === auth()->id()) {
            $this->redirectRoute('app.dashboard');
        }
    }

    public function deleteOrganization()
    {
        abort_unless(auth()->user()->ownsOrganization($this->organization), 403);
        $this->organization->delete();

        return redirect()->route('onboarding')->with('status', 'Organization deleted.');
    }

    protected function isLastOwner(int $userId): bool
    {
        $owners = $this->organization->users()->wherePivot('role', 'owner')->pluck('users.id');

        return $owners->count() === 1 && (int) $owners->first() === $userId;
    }

    public function render()
    {
        return view('livewire.organization.settings', [
            'members' => $this->organization->users()->orderBy('name')->get(),
            'invitations' => $this->organization->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
            'canManage' => auth()->user()->canManageOrganization($this->organization),
            'isOwner' => auth()->user()->ownsOrganization($this->organization),
        ])->layoutData(['title' => 'Organization']);
    }
}
