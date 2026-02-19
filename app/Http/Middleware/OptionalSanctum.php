<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate user via Sanctum if Bearer token is present; do not abort if missing.
 * Use for routes that support both guest and logged-in users (e.g. guest checkout).
 */
class OptionalSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->tokenable) {
                Auth::setUser($accessToken->tokenable);
            }
        }

        return $next($request);
    }
}
