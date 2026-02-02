<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Checks both Spatie Permission roles and the role field in users table
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user has any of the required roles via Spatie Permission OR role field
        foreach ($roles as $role) {
            // First check the role field (faster and more reliable)
            if ($user->role === $role) {
                return $next($request);
            }
            
            // Then check Spatie Permission (with error handling)
            try {
                if ($user->hasRole($role)) {
                    return $next($request);
                }
            } catch (\Exception $e) {
                // If Spatie role check fails, continue to next check
                // The role field check above should handle most cases
                continue;
            }
        }

        // User doesn't have required role - redirect to login or show 403
        abort(403, 'Unauthorized access. You do not have the required role.');
    }
}

