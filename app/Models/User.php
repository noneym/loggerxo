<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'current_organization_id',
        'is_super_admin',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('role')->withTimestamps();
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function roleIn(Organization $organization): ?string
    {
        if ($organization->relationLoaded('users')) {
            $member = $organization->users->firstWhere('id', $this->id);

            return $member?->pivot?->role;
        }

        $pivot = $this->organizations()->where('organizations.id', $organization->id)->first()?->pivot;

        return $pivot?->role;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->is_super_admin || $this->roleIn($organization) !== null;
    }

    public function canManageOrganization(Organization $organization): bool
    {
        return $this->is_super_admin || in_array($this->roleIn($organization), ['owner', 'admin'], true);
    }

    public function ownsOrganization(Organization $organization): bool
    {
        return $this->is_super_admin || $this->roleIn($organization) === 'owner';
    }

    public function canAccessProject(Project $project): bool
    {
        return $this->belongsToOrganization($project->organization);
    }

    public function canManageProject(Project $project): bool
    {
        return $this->canManageOrganization($project->organization);
    }

    /**
     * Resolve the organization the user is currently working in,
     * falling back to the first membership.
     */
    public function resolveCurrentOrganization(): ?Organization
    {
        if ($this->current_organization_id) {
            $org = $this->currentOrganization;
            if ($org && $this->belongsToOrganization($org)) {
                return $org;
            }
        }

        $first = $this->organizations()->orderBy('organizations.id')->first();
        if ($first) {
            $this->forceFill(['current_organization_id' => $first->id])->saveQuietly();
            $this->setRelation('currentOrganization', $first);
        }

        return $first;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($this->email, 0, 1));
    }
}
