<?php

namespace App\Services\Vendor;

use App\Enums\VendorProductApprovalStatus;
use App\Models\User;
use App\Models\VendorProduct;
use App\Support\MarketplaceSettings;

class AdminVendorProductService
{
    public function approve(VendorProduct $vp, User $admin, ?string $notes = null): VendorProduct
    {
        $vp->update([
            'approval_status' => VendorProductApprovalStatus::Approved->value,
            'rejection_reason' => null,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'status' => 'active',
        ]);
        $vp->product?->update(['status' => 'active']);

        return $vp->fresh(['product', 'vendor.profile']);
    }

    public function reject(VendorProduct $vp, User $admin, string $reason): VendorProduct
    {
        $vp->update([
            'approval_status' => VendorProductApprovalStatus::Rejected->value,
            'rejection_reason' => $reason,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'status' => 'inactive',
        ]);
        $vp->product?->update(['status' => 'archived']);

        return $vp->fresh(['product', 'vendor.profile']);
    }

    public function removeListing(VendorProduct $vp): void
    {
        $vp->delete();
        $vp->product?->update(['status' => 'archived']);
    }

    public static function initialApprovalStatus(): string
    {
        return MarketplaceSettings::productApprovalRequired()
            ? VendorProductApprovalStatus::Pending->value
            : VendorProductApprovalStatus::Approved->value;
    }
}
