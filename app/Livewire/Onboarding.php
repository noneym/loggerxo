<?php

namespace App\Livewire;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Create organization')]
class Onboarding extends Component
{
    public string $name = '';

    public bool $hasOrganizations = false;

    public function mount(): void
    {
        $this->hasOrganizations = auth()->user()->organizations()->exists();
        if ($this->name === '' && ! $this->hasOrganizations) {
            $this->name = auth()->user()->name."'s team";
        }
    }

    public function create()
    {
        $this->validate(['name' => ['required', 'string', 'min:2', 'max:120']]);

        $user = auth()->user();
        $organization = DB::transaction(function () use ($user) {
            $organization = Organization::create([
                'name' => $this->name,
                'slug' => Organization::uniqueSlug($this->name),
                'owner_id' => $user->id,
            ]);
            $organization->addMember($user, 'owner');
            $user->forceFill(['current_organization_id' => $organization->id])->save();

            return $organization;
        });

        return redirect()->route('projects.create')->with('status', $organization->name.' created. Now add your first project.');
    }

    public function render()
    {
        return view('livewire.onboarding');
    }
}
