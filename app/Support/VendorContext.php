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
        return $user->vendor()->with('profile')->first();
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
