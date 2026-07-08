<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Checks Spatie roles, users.role (case-insensitive), and portal-scoped API tokens.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if ($user === null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        // Support pipe-separated roles (e.g. role:client|admin)
        $allRoles = [];
        foreach ($roles as $role) {
            $allRoles = array_merge($allRoles, array_map('trim', explode('|', $role)));
        }
        $roles = array_values(array_unique(array_filter($allRoles)));

        foreach ($roles as $role) {
            if ($user->hasAppRole($role)) {
                return $next($request);
            }
        }

        $token = $user->currentAccessToken();
        if ($this->portalTokenGrantsAnyRole($token, $roles)) {
            return $next($request);
        }

        // Legacy Sanctum tokens (api_token with wildcard ability) — allow when user has the app role.
        if ($token instanceof PersonalAccessToken && $this->legacyTokenGrantsAnyRole($token, $roles, $user)) {
            return $next($request);
        }

        abort(403, 'Unauthorized access. You do not have the required role.');
    }

    /**
     * @param  list<string>  $roles
     */
    private function legacyTokenGrantsAnyRole(PersonalAccessToken $token, array $roles, $user): bool
    {
        $abilities = $token->abilities ?? [];
        if (! in_array('*', $abilities, true)) {
            return false;
        }

        $name = $token->name;
        if (! is_string($name) || $name !== 'api_token') {
            return false;
        }

        foreach ($roles as $role) {
            if ($user->hasAppRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Honor login portal tokens (api_client, api_technician, …) scoped to a single role ability.
     * Legacy generic tokens (api_token with wildcard abilities) still rely on hasAppRole().
     *
     * @param  list<string>  $roles
     */
    private function portalTokenGrantsAnyRole(?PersonalAccessToken $token, array $roles): bool
    {
        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        $name = $token->name;
        if (! is_string($name) || ! str_starts_with($name, 'api_')) {
            return false;
        }

        $abilities = $token->abilities ?? [];
        if ($abilities === [] || in_array('*', $abilities, true)) {
            return false;
        }

        foreach ($roles as $role) {
            if ($token->can($role)) {
                return true;
            }
        }

        return false;
    }
}
