<?php

namespace App\Livewire\Concerns;

use App\Models\Project;
use Livewire\Attributes\Locked;

trait ResolvesProject
{
    #[Locked]
    public Project $project;

    protected function authorizeProject(Project $project, bool $manage = false): void
    {
        $user = auth()->user();
        abort_unless($user->canAccessProject($project), 404);
        if ($manage) {
            abort_unless($user->canManageProject($project), 403);
        }
        if ($user->current_organization_id !== $project->organization_id && ! $user->is_super_admin) {
            $user->forceFill(['current_organization_id' => $project->organization_id])->save();
        } elseif ($user->is_super_admin && $user->current_organization_id !== $project->organization_id) {
            $user->forceFill(['current_organization_id' => $project->organization_id])->save();
        }
        $this->project = $project;
    }

    protected function layoutFor(string $title): array
    {
        return ['title' => $title, 'project' => $this->project];
    }
}
