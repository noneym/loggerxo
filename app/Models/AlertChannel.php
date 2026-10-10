<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertChannel extends Model
{
    public const TYPES = [
        'email' => 'E-mail',
        'telegram' => 'Telegram',
    ];

    protected $fillable = ['project_id', 'type', 'name', 'config', 'is_active', 'last_used_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function summary(): string
    {
        return match ($this->type) {
            'email' => implode(', ', (array) data_get($this->config, 'emails', [])),
            'telegram' => 'chat '.data_get($this->config, 'chat_id', '?'),
            default => '',
        };
    }
}
