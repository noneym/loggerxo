<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'project_id', 'fingerprint', 'type', 'level', 'platform', 'title', 'culprit', 'status', 'is_regressed',
        'first_seen', 'last_seen', 'events_count', 'last_event_id', 'environments', 'metadata',
        'resolved_at', 'resolved_by_id', 'assigned_to_id', 'ignored_until',
    ];

    protected function casts(): array
    {
        return [
            'is_regressed' => 'boolean',
            'first_seen' => 'datetime',
            'last_seen' => 'datetime',
            'resolved_at' => 'datetime',
            'ignored_until' => 'datetime',
            'environments' => 'array',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class)->latest();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function resolve(?User $by = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_RESOLVED,
            'is_regressed' => false,
            'resolved_at' => now(),
            'resolved_by_id' => $by?->id,
        ])->save();
    }

    public function ignore(?\DateTimeInterface $until = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_IGNORED,
            'ignored_until' => $until,
        ])->save();
    }

    public function reopen(): void
    {
        $this->forceFill([
            'status' => self::STATUS_OPEN,
            'resolved_at' => null,
            'resolved_by_id' => null,
            'ignored_until' => null,
        ])->save();
    }

    public function shortId(): string
    {
        return strtoupper(substr($this->fingerprint, 0, 8));
    }

    public function levelColor(): string
    {
        return match ($this->level) {
            'emergency', 'alert', 'critical' => 'rose',
            'error' => 'red',
            'warning' => 'amber',
            'notice', 'info' => 'sky',
            default => 'zinc',
        };
    }

    public function url(): string
    {
        return route('issues.show', ['project' => $this->project_id, 'issue' => $this->id]);
    }
}
