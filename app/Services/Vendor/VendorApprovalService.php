<?php

namespace App\Services\Vendor;

use App\Enums\VendorStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use App\Models\VendorInventory;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use App\Services\Vendor\VendorDocumentService;
use Illuminate\Support\Facades\DB;

class VendorApprovalService
{
    public function __construct(
        private readonly VendorVendorNotifier $vendorNotifier
    ) {}

    public function transition(Vendor $vendor, VendorStatus $newStatus, ?User $admin, ?string $notes = null, ?string $rejectionReason = null): Vendor
    {
        $vendor = DB::transaction(function () use ($vendor, $newStatus, $admin, $notes, $rejectionReason) {
            $old = $vendor->status;
            $vendor->status = $newStatus->value;
            $vendor->rejection_reason = null;

            match ($newStatus) {
                VendorStatus::Approved => $vendor->fill([
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'suspended_at' => null,
                ]),
                VendorStatus::Rejected => $vendor->fill([
                    'rejected_at' => now(),
                    'rejection_reason' => $rejectionReason,
                ]),
                VendorStatus::Suspended => $vendor->fill(['suspended_at' => now()]),
                VendorStatus::Disabled => $vendor->fill(['suspended_at' => now()]),
                VendorStatus::Pending, VendorStatus::UnderReview => $vendor->fill([
                    'approved_at' => null,
                    'rejected_at' => null,
                    'suspended_at' => null,
                ]),
            };

            $vendor->save();

            if ($newStatus === VendorStatus::Disabled && $vendor->user) {
                $vendor->user->update(['status' => 'inactive']);
            }
            if ($newStatus === VendorStatus::Approved && $vendor->user) {
                $vendor->user->update(['status' => 'active']);
            }

            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => $admin?->id,
                'action' => $newStatus->value,
                'old_status' => $old,
                'new_status' => $newStatus->value,
                'notes' => $notes,
            ]);

            return $vendor->fresh(['profile', 'user', 'approvalLogs']);
        });

        if ($newStatus === VendorStatus::Approved) {
            $this->vendorNotifier->approved($vendor, $notes);
        } elseif ($newStatus === VendorStatus::Rejected) {
            $this->vendorNotifier->rejected($vendor, $rejectionReason ?? 'Not specified.', $notes);
        }

        return $vendor;
    }

    public function approve(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Approved, $admin, $notes);
    }

    public function reject(Vendor $vendor, User $admin, string $reason, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Rejected, $admin, $notes, $reason);
    }

    public function suspend(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Suspended, $admin, $notes);
    }

    public function underReview(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::UnderReview, $admin, $notes);
    }

    public function disable(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Disabled, $admin, $notes);
    }

    public function activate(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Approved, $admin, $notes ?? 'Reactivated by admin.');
    }

    /**
     * Permanently remove vendor account, profile, documents, and linked user.
     */
    public function permanentlyDelete(Vendor $vendor, User $admin, ?string $notes = null): void
    {
        DB::transaction(function () use ($vendor, $admin, $notes) {
            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => $admin->id,
                'action' => 'deleted',
                'old_status' => $vendor->status,
                'new_status' => 'deleted',
                'notes' => $notes ?? 'Permanently deleted by admin.',
            ]);

            $vendor->documents()->each(function ($doc) {
                app(VendorDocumentService::class)->delete($doc);
            });

            $vendorProductIds = VendorProduct::withTrashed()
                ->where('vendor_id', $vendor->id)
                ->pluck('id');

            if ($vendorProductIds->isNotEmpty()) {
                VendorProductPrice::whereIn('vendor_product_id', $vendorProductIds)->delete();
                VendorInventory::whereIn('vendor_product_id', $vendorProductIds)->delete();
                VendorProduct::withTrashed()->where('vendor_id', $vendor->id)->forceDelete();
            }

            VendorOrderMapping::where('vendor_id', $vendor->id)->delete();

            $productIds = Product::where('vendor_id', $vendor->id)->pluck('id');
            if ($productIds->isNotEmpty()) {
                $orderIds = OrderItem::whereIn('product_id', $productIds)->pluck('order_id')->unique();
                OrderItem::whereIn('product_id', $productIds)->delete();
                foreach ($orderIds as $orderId) {
                    if (OrderItem::where('order_id', $orderId)->count() === 0) {
                        Order::where('id', $orderId)->delete();
                    }
                }
                Product::whereIn('id', $productIds)->delete();
            }

            $vendor->categories()->detach();

            $user = $vendor->user;
            $vendor->forceDelete();
            if ($user) {
                $user->tokens()->delete();
                $user->delete();
            }
        });
    }
}
