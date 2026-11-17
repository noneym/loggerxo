<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Personal access token. Acts as its user for read-only API access (the MCP endpoint).
 */
class ApiToken extends Model
{
    public const PREFIX = 'lxp_';

    protected $fillable = ['user_id', 'name', 'token_hash', 'token_hint', 'last_used_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Create a token and return it with the plain value, which is not stored and cannot be read again.
     *
     * @return array{0: ApiToken, 1: string}
     */
    public static function issue(User $user, string $name): array
    {
        $plain = self::PREFIX.Str::random(40);
        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'token_hint' => substr($plain, -4),
        ]);

        return [$token, $plain];
    }

    public static function findByPlain(?string $plain): ?ApiToken
    {
        if (! $plain || ! preg_match('/^'.self::PREFIX.'[A-Za-z0-9]{40}$/', $plain)) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $plain))->first();
    }

    /**
     * Record usage at most once a minute so busy clients do not write on every request.
     */
    public function touchLastUsed(): void
    {
        if (! $this->last_used_at || $this->last_used_at->lt(now()->subMinute())) {
            $this->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
