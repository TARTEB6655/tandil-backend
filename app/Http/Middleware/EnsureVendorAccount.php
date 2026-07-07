<?php

namespace App\Http\Middleware;

use App\Support\VendorContext;
use App\Support\VendorLoginGate;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isVendor()) {
            abort(403, 'Vendor access required.');
        }

        $vendor = VendorContext::vendorForUser($user);
        if ($vendor === null) {
            abort(403, 'Vendor profile not found.');
        }

        $blocked = VendorLoginGate::blockedMessageForVendor($vendor);
        if ($blocked !== null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $blocked,
                    'data' => ['status' => $vendor->status],
                ], 403);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('app-portal.login', ['portal' => 'vendor'])
                ->withErrors(['email' => $blocked]);
        }

        $request->attributes->set('vendor', $vendor->loadMissing('profile'));

        return $next($request);
    }
}
