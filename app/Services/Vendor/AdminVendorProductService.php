<?php

namespace App\Services\Vendor;

use App\Enums\VendorProductApprovalStatus;
use App\Models\User;
use App\Models\VendorProduct;
use App\Support\MarketplaceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'disabled_by_admin' => false,
            'disabled_by_admin_at' => null,
            'disabled_by_admin_by' => null,
            'admin_disable_reason' => null,
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

    public function disableByAdmin(VendorProduct $vp, User $admin, ?string $reason = null): VendorProduct
    {
        $vp->update([
            'status' => 'inactive',
            'disabled_by_admin' => true,
            'disabled_by_admin_at' => now(),
            'disabled_by_admin_by' => $admin->id,
            'admin_disable_reason' => $reason,
        ]);
        $vp->product?->update(['status' => 'archived']);

        return $vp->fresh(['product', 'vendor.profile']);
    }

    public function enableByAdmin(VendorProduct $vp, User $admin): VendorProduct
    {
        if ($vp->approval_status !== VendorProductApprovalStatus::Approved->value) {
            throw ValidationException::withMessages([
                'product' => 'Product must be approved before it can be enabled on the marketplace.',
            ]);
        }

        $vp->update([
            'status' => 'active',
            'disabled_by_admin' => false,
            'disabled_by_admin_at' => null,
            'disabled_by_admin_by' => null,
            'admin_disable_reason' => null,
        ]);
        $vp->product?->update(['status' => 'active']);

        return $vp->fresh(['product', 'vendor.profile']);
    }

    public function toggle(VendorProduct $vp, User $admin): VendorProduct
    {
        if ($vp->status === 'active' && ! $vp->disabled_by_admin) {
            return $this->disableByAdmin($vp, $admin);
        }

        return $this->enableByAdmin($vp, $admin);
    }

    public function removeListing(VendorProduct $vp): void
    {
        $vp->delete();
        $vp->product?->update(['status' => 'archived']);
    }

    /**
     * @param  list<int>  $productIds
     * @return array{processed: int, failed: int}
     */
    public function bulk(int $vendorId, array $productIds, string $action, User $admin, ?string $reason = null): array
    {
        $products = VendorProduct::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('id', $productIds)
            ->get();

        $processed = 0;
        $failed = 0;

        DB::transaction(function () use ($products, $action, $admin, $reason, &$processed, &$failed) {
            foreach ($products as $vp) {
                try {
                    match ($action) {
                        'approve' => $this->approve($vp, $admin),
                        'reject' => $this->reject($vp, $admin, $reason ?? 'Bulk rejected by admin'),
                        'enable' => $this->enableByAdmin($vp, $admin),
                        'disable' => $this->disableByAdmin($vp, $admin, $reason),
                        'delete' => $this->removeListing($vp),
                        default => throw new \InvalidArgumentException('Invalid bulk action'),
                    };
                    $processed++;
                } catch (\Throwable) {
                    $failed++;
                }
            }
        });

        return ['processed' => $processed, 'failed' => $failed];
    }

    public static function initialApprovalStatus(): string
    {
        return MarketplaceSettings::productApprovalRequired()
            ? VendorProductApprovalStatus::Pending->value
            : VendorProductApprovalStatus::Approved->value;
    }
}
