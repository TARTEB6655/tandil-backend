<?php

namespace App\Support;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;

class VendorLoginGate
{
    /**
     * Human-readable block reason, or null when the vendor may sign in.
     */
    public static function blockedMessageForUser(User $user): ?string
    {
        if (! $user->hasAppRole('vendor')) {
            return null;
        }

        $vendor = $user->vendor()->first();
        if ($vendor === null) {
            return 'Vendor profile not found. Please contact support.';
        }

        return self::blockedMessageForVendor($vendor);
    }

    public static function blockedMessageForVendor(Vendor $vendor): ?string
    {
        if ($vendor->isApproved()) {
            return null;
        }

        return match ($vendor->statusEnum()) {
            VendorStatus::Pending, VendorStatus::UnderReview, VendorStatus::Rejected => null,
            VendorStatus::Suspended => 'Your vendor account has been suspended. Please contact admin.',
            VendorStatus::Disabled => 'Your vendor account has been disabled. Please contact admin.',
            default => null,
        };
    }

    public static function isLoginAllowed(User $user): bool
    {
        return self::blockedMessageForUser($user) === null;
    }
}
