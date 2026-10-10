<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Project extends Model
{
    public const PLATFORMS = [
        'laravel' => 'Laravel',
        'browser' => 'Browser / JavaScript',
        'php' => 'PHP (generic)',
        'other' => 'Other (HTTP API)',
    ];

    protected $fillable = [
        'organization_id', 'name', 'slug', 'platform', 'public_key', 'allowed_origins',
        'retention_days', 'settings', 'is_active', 'last_event_at',
    ];

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_event_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->public_key ??= static::generateKey();
            $project->slug ??= static::uniqueSlug($project->organization_id, $project->name);
            $project->retention_days ??= config('loggerxo.default_retention_days');
        });

        static::saved(fn (Project $project) => $project->forgetKeyCache());
        static::deleted(fn (Project $project) => $project->forgetKeyCache());
    }

    public static function generateKey(): string
    {
        do {
            $key = 'lx_'.Str::lower(Str::random(32));
        } while (static::where('public_key', $key)->exists());

        return $key;
    }

    public static function uniqueSlug(int $organizationId, string $name): string
    {
        $base = Str::slug(Str::limit($name, 60, '')) ?: 'project';
        $slug = $base;
        $i = 2;
        while (static::where('organization_id', $organizationId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public static function cacheKeyFor(string $publicKey): string
    {
        return 'lx:project-key:'.$publicKey;
    }

    /**
     * Resolve a project by its public key with a short cache (used by the ingest API).
     */
    public static function findByKeyCached(string $publicKey): ?Project
    {
        if (! preg_match('/^lx_[a-z0-9]{32}$/', $publicKey)) {
            return null;
        }

        $data = Cache::remember(static::cacheKeyFor($publicKey), 300, function () use ($publicKey) {
            $project = static::where('public_key', $publicKey)->first();

            return $project ? $project->getAttributes() : ['__missing' => true];
        });

        if (isset($data['__missing'])) {
            return null;
        }

        $project = (new static)->newFromBuilder($data);

        return $project;
    }

    public function forgetKeyCache(): void
    {
        if ($this->public_key) {
            Cache::forget(static::cacheKeyFor($this->public_key));
        }
        if ($this->getOriginal('public_key') && $this->getOriginal('public_key') !== $this->public_key) {
            Cache::forget(static::cacheKeyFor($this->getOriginal('public_key')));
        }
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function alertChannels(): HasMany
    {
        return $this->hasMany(AlertChannel::class);
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }

    public function platformLabel(): string
    {
        return static::PLATFORMS[$this->platform] ?? ucfirst($this->platform);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function ingestUrl(): string
    {
        return rtrim(config('app.url'), '/').'/api/v1/envelope';
    }

    public function dsn(): string
    {
        $url = parse_url(config('app.url'));
        $host = $url['host'] ?? 'loggerxo.com';
        $scheme = $url['scheme'] ?? 'https';
        $port = isset($url['port']) ? ':'.$url['port'] : '';

        return sprintf('%s://%s@%s%s', $scheme, $this->public_key, $host, $port);
    }

    public function originAllowed(?string $origin): bool
    {
        $allowed = array_values(array_filter((array) $this->allowed_origins));
        if ($allowed === []) {
            return true;
        }
        if (! $origin) {
            return false;
        }
        $origin = rtrim(strtolower($origin), '/');
        foreach ($allowed as $pattern) {
            $pattern = rtrim(strtolower(trim($pattern)), '/');
            if ($pattern === '*' || $pattern === $origin) {
                return true;
            }
            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1); // ".example.com"
                $host = parse_url($origin, PHP_URL_HOST) ?: $origin;
                if (str_ends_with($host, $suffix) || $host === ltrim($suffix, '.')) {
                    return true;
                }
            }
        }

        return false;
    }
}
