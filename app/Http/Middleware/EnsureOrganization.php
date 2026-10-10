<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes sure the authenticated user has a current organization; otherwise sends them to onboarding.
 */
class EnsureOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $organization = $user->resolveCurrentOrganization();
        if (! $organization && ! $request->routeIs('onboarding*')) {
            return redirect()->route('onboarding');
        }
        if ($organization) {
            app()->instance('currentOrganization', $organization);
            view()->share('currentOrganization', $organization);
        }

        return $next($request);
    }
}
