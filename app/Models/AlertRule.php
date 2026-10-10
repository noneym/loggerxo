<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    public const TRIGGERS = [
        'new_issue' => 'A new issue is created',
        'regression' => 'A resolved issue happens again',
        'frequency' => 'An issue is seen more than N times in a time window',
        'log_level' => 'Any event at or above a log level',
    ];

    protected $fillable = [
        'project_id', 'name', 'trigger', 'conditions', 'channel_ids', 'cooldown_minutes', 'is_active', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'channel_ids' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function triggerLabel(): string
    {
        return self::TRIGGERS[$this->trigger] ?? $this->trigger;
    }

    public function condition(string $key, mixed $default = null): mixed
    {
        return data_get($this->conditions, $key, $default);
    }

    public function describe(): string
    {
        $env = $this->condition('environments');
        $envText = is_array($env) && $env !== [] ? ' in '.implode(', ', $env) : '';

        return match ($this->trigger) {
            'new_issue' => 'New issue'.$envText,
            'regression' => 'Regression'.$envText,
            'frequency' => sprintf('More than %d events in %d minutes%s', $this->condition('threshold', 10), $this->condition('window_minutes', 5), $envText),
            'log_level' => sprintf('Level >= %s%s', $this->condition('min_level', 'critical'), $envText),
            default => $this->trigger,
        };
    }
}
