<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Some Apache / PHP-FPM hosts strip the Authorization header before Laravel sees it.
 * Normalize Bearer tokens (trim, quotes, duplicate "Bearer" prefix).
 */
class ResolveAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header('Authorization')
            ?? $request->server('HTTP_AUTHORIZATION')
            ?? $request->server('REDIRECT_HTTP_AUTHORIZATION')
            ?? $request->header('X-Authorization')
            ?? $request->server('HTTP_X_AUTHORIZATION');

        if (! is_string($raw) || trim($raw) === '') {
            return $next($request);
        }

        $normalized = $this->normalizeBearerHeader($raw);
        if ($normalized !== null) {
            $request->headers->set('Authorization', $normalized);
        }

        return $next($request);
    }

    private function normalizeBearerHeader(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        // Strip wrapping quotes some mobile clients add.
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = trim($value, "\"'");
        }

        // Accept raw token without the Bearer prefix.
        if (! str_starts_with(strtolower($value), 'bearer ')) {
            $token = trim($value);
            if ($token === '') {
                return null;
            }

            return 'Bearer '.$token;
        }

        // Collapse accidental "Bearer Bearer <token>".
        while (preg_match('/^Bearer\s+Bearer\s+/i', $value)) {
            $value = preg_replace('/^Bearer\s+/i', '', $value, 1) ?? $value;
        }

        if (! preg_match('/^Bearer\s+(\S.+)$/i', $value, $matches)) {
            return null;
        }

        $token = trim($matches[1], "\"' ");
        if ($token === '') {
            return null;
        }

        return 'Bearer '.$token;
    }
}
