<?php

namespace App\Http\Middleware;

use App\Support\VendorContext;
use Closure;
use Illuminate\Http\Request;
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

        $request->attributes->set('vendor', $vendor->loadMissing('profile'));

        return $next($request);
    }
}
