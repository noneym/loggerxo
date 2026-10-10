<?php

namespace App\Providers;

use App\Mail\Transport\CloudflareEmailTransport;
use App\Services\ClickHouse\ClickHouseClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClickHouseClient::class, fn () => ClickHouseClient::fromConfig());
    }

    public function boot(): void
    {
        Model::unguard(false);
        Model::preventLazyLoading(! $this->app->isProduction());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Mail::extend('cloudflare', function (array $config) {
            return new CloudflareEmailTransport(
                accountId: (string) ($config['account_id'] ?? ''),
                token: (string) ($config['token'] ?? ''),
                baseUrl: (string) ($config['base_url'] ?? 'https://api.cloudflare.com/client/v4'),
                timeout: (int) ($config['timeout'] ?? 20),
            );
        });
    }
}
