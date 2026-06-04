<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VendorOrderService
{
    public function listForVendor(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $q = VendorOrderMapping::with(['order.user', 'order.items.product'])
            ->where('vendor_id', $vendor->id);

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $q->whereHas('order', function ($oq) use ($search) {
                $oq->where('id', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhere('guest_full_name', 'like', "%{$search}%");
            });
        }

        return $q->latest()->paginate($perPage);
    }

    public function updateStatus(VendorOrderMapping $mapping, VendorOrderStatus $status, User $user, ?string $note = null): VendorOrderMapping
    {
        return DB::transaction(function () use ($mapping, $status, $user, $note) {
            $mapping->update(['status' => $status->value]);

            VendorOrderStatusLog::create([
                'vendor_order_mapping_id' => $mapping->id,
                'status' => $status->value,
                'changed_by' => $user->id,
                'note' => $note,
            ]);

            return $mapping->fresh(['order.items.product', 'statusLogs']);
        });
    }
}
