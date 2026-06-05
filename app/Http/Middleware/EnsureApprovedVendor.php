<?php

namespace App\Http\Middleware;

use App\Enums\VendorStatus;
use App\Support\VendorContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedVendor
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

        if ($vendor->status !== VendorStatus::Approved->value) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your vendor account is '.$vendor->statusEnum()->label().'. Please wait for admin approval.',
                    'data' => ['status' => $vendor->status],
                ], 403);
            }

            return redirect()->route('vendor.application.status');
        }

        $request->attributes->set('vendor', $vendor);

        return $next($request);
    }
}
