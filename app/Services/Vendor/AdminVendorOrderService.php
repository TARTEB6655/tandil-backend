<?php

namespace App\Services\Vendor;

use App\Enums\VendorDisputeStatus;
use App\Enums\VendorOrderStatus;
use App\Models\User;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Support\MarketplaceSettings;
use Illuminate\Support\Facades\DB;

class AdminVendorOrderService
{
    public function updateStatus(VendorOrderMapping $mapping, VendorOrderStatus $status, User $admin, ?string $note = null): VendorOrderMapping
    {
        return DB::transaction(function () use ($mapping, $status, $admin, $note) {
            $mapping->update(['status' => $status->value]);

            VendorOrderStatusLog::create([
                'vendor_order_mapping_id' => $mapping->id,
                'status' => $status->value,
                'changed_by' => $admin->id,
                'note' => $note ?? 'Updated by admin.',
            ]);

            if ($status === VendorOrderStatus::Cancelled) {
                $mapping->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => $admin->id,
                ]);
            }

            return $mapping->fresh(['order.user', 'vendor.profile', 'statusLogs']);
        });
    }

    public function cancel(VendorOrderMapping $mapping, User $admin, string $reason): VendorOrderMapping
    {
        $mapping->update(['cancellation_reason' => $reason]);

        return $this->updateStatus($mapping, VendorOrderStatus::Cancelled, $admin, $reason);
    }

    public function updateDispute(VendorOrderMapping $mapping, VendorDisputeStatus $status, User $admin, ?string $notes = null): VendorOrderMapping
    {
        $mapping->update([
            'dispute_status' => $status->value,
            'dispute_notes' => $notes,
        ]);

        return $mapping->fresh(['order.user', 'vendor.profile']);
    }

    public function recalculateCommission(VendorOrderMapping $mapping): VendorOrderMapping
    {
        $rate = MarketplaceSettings::effectiveCommissionForVendor($mapping->vendor);
        $commission = round((float) $mapping->total_amount * ($rate / 100), 2);
        $mapping->update(['commission_amount' => $commission]);

        return $mapping;
    }
}
