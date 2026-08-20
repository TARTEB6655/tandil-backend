<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class VendorContext
{
    public static function vendorForUser(User $user): ?Vendor
    {
        $cacheKey = 'vendor_context.user.'.$user->id;

        if (app()->bound('request')) {
            $request = request();
            if ($request->attributes->has($cacheKey)) {
                return $request->attributes->get($cacheKey);
            }
        }

        $vendor = $user->vendor()->with('profile')->first();

        if (app()->bound('request')) {
            request()->attributes->set($cacheKey, $vendor);
        }

        return $vendor;
    }

    public static function approvedVendorForUser(User $user): ?Vendor
    {
        $vendor = self::vendorForUser($user);

        return ($vendor && $vendor->isApproved()) ? $vendor : null;
    }

    public static function requireApprovedVendor(User $user): Vendor
    {
        $vendor = self::approvedVendorForUser($user);
        if ($vendor === null) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Vendor account is not approved or does not exist.',
            ], 403));
        }

        return $vendor;
    }
}
