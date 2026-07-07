<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Some Apache / PHP-FPM hosts strip the Authorization header before Laravel sees it.
 * Restore Bearer tokens from server variables when needed.
 */
class ResolveAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $header = $request->header('Authorization')
                ?? $request->server('HTTP_AUTHORIZATION')
                ?? $request->server('REDIRECT_HTTP_AUTHORIZATION');

            if (is_string($header) && trim($header) !== '') {
                $request->headers->set('Authorization', trim($header));
            }
        }

        return $next($request);
    }
}
