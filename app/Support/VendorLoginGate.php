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
            VendorStatus::Rejected => 'Your vendor application was rejected.'
                .($vendor->rejection_reason ? ' Reason: '.$vendor->rejection_reason : ' Please contact admin.'),
            VendorStatus::Suspended => 'Your vendor account has been suspended. Please contact admin.',
            VendorStatus::Disabled => 'Your vendor account has been disabled. Please contact admin.',
            VendorStatus::UnderReview => 'Your vendor application is under admin review. You can sign in after approval.',
            default => 'Your vendor account is pending admin approval. You can sign in after approval.',
        };
    }

    public static function isLoginAllowed(User $user): bool
    {
        return self::blockedMessageForUser($user) === null;
    }
}
