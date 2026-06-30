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

    /**
     * @return array<string, int>
     */
    public function statusSummary(Vendor $vendor): array
    {
        $rows = VendorOrderMapping::query()
            ->where('vendor_id', $vendor->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $rows->sum();
        $delivered = (int) ($rows[VendorOrderStatus::Delivered->value] ?? 0);

        return [
            'total' => $total,
            'pending' => (int) ($rows[VendorOrderStatus::Pending->value] ?? 0),
            'confirmed' => (int) ($rows[VendorOrderStatus::Confirmed->value] ?? 0),
            'processing' => (int) ($rows[VendorOrderStatus::Processing->value] ?? 0),
            'shipped' => (int) ($rows[VendorOrderStatus::Shipped->value] ?? 0),
            'delivered' => $delivered,
            'cancelled' => (int) ($rows[VendorOrderStatus::Cancelled->value] ?? 0),
        ];
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
