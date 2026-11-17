<?php

use App\Http\Controllers\Api\McpController;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureOrganization;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // MCP endpoint for AI clients: stateless like the API, but at /mcp rather than under /api.
            Route::middleware(['api', 'api.token'])->post('/mcp', McpController::class)->name('mcp');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/app');
        $middleware->alias([
            'org' => EnsureOrganization::class,
            'superadmin' => EnsureSuperAdmin::class,
            'api.token' => AuthenticateApiToken::class,
        ]);
        $middleware->throttleApi('600,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
