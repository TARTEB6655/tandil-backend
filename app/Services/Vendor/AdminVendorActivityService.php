<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use App\Models\VendorInventoryLog;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Models\VendorProduct;
use Illuminate\Support\Collection;

class AdminVendorActivityService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(Vendor $vendor, int $limit = 50): array
    {
        $events = collect();

        $vendor->approvalLogs()
            ->with('performer')
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (VendorApprovalLog $log) use ($events) {
                $events->push([
                    'type' => 'approval',
                    'title' => ucfirst(str_replace('_', ' ', $log->action)),
                    'description' => "Status changed from {$log->old_status} to {$log->new_status}",
                    'actor' => $log->performer?->name,
                    'at' => $log->created_at,
                ]);
            });

        VendorProduct::query()
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->limit(15)
            ->with('product')
            ->get()
            ->each(function (VendorProduct $vp) use ($events) {
                $events->push([
                    'type' => 'product',
                    'title' => 'Product listed',
                    'description' => $vp->product?->name ?? 'Product #'.$vp->product_id,
                    'actor' => null,
                    'at' => $vp->created_at,
                ]);
            });

        VendorOrderMapping::query()
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->limit(15)
            ->get()
            ->each(function (VendorOrderMapping $mapping) use ($events) {
                $events->push([
                    'type' => 'order',
                    'title' => 'Order received',
                    'description' => 'Order #'.$mapping->order_id.' · AED '.number_format((float) $mapping->total_amount, 2),
                    'actor' => null,
                    'at' => $mapping->created_at,
                ]);
            });

        VendorOrderStatusLog::query()
            ->whereHas('mapping', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with('mapping')
            ->latest()
            ->limit(15)
            ->get()
            ->each(function (VendorOrderStatusLog $log) use ($events) {
                if ($log->status === VendorOrderStatus::Delivered->value) {
                    $events->push([
                        'type' => 'order',
                        'title' => 'Order completed',
                        'description' => 'Order #'.($log->mapping?->order_id ?? '—'),
                        'actor' => null,
                        'at' => $log->created_at,
                    ]);
                }
            });

        return $events
            ->filter(fn ($e) => $e['at'] !== null)
            ->sortByDesc('at')
            ->take($limit)
            ->map(fn ($e) => array_merge($e, [
                'at_iso' => $e['at']?->toIso8601String(),
                'at_formatted' => $e['at']?->format('M j, Y g:i A'),
            ]))
            ->values()
            ->all();
    }
}
