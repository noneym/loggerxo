<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Create extends Component
{
    public string $name = '';

    public string $platform = 'laravel';

    public function mount(): void
    {
        abort_unless(auth()->user()->canManageOrganization(auth()->user()->resolveCurrentOrganization()), 403);
    }

    public function create()
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'platform' => ['required', 'in:'.implode(',', array_keys(Project::PLATFORMS))],
        ]);
        $org = auth()->user()->resolveCurrentOrganization();
        $project = Project::create([
            'organization_id' => $org->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'retention_days' => config('loggerxo.default_retention_days'),
        ]);

        return redirect()->route('projects.settings', [$project, 'tab' => 'setup'])->with('status', 'Project created. Install the SDK to start receiving events.');
    }

    public function render()
    {
        return view('livewire.projects.create', ['platforms' => Project::PLATFORMS])->layoutData(['title' => 'New project']);
    }
}
