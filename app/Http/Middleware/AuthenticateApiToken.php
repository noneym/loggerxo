<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a request with a personal access token in `Authorization: Bearer lxp_…`.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findByPlain($request->bearerToken());
        $user = $token?->user;
        if (! $user) {
            return response()->json(
                ['error' => 'unauthorized', 'message' => 'Send a personal access token as "Authorization: Bearer lxp_…". Create one under Account → API tokens.'],
                401,
                ['WWW-Authenticate' => 'Bearer realm="loggerxo"']
            );
        }

        $token->touchLastUsed();
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
