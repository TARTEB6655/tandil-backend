<?php

namespace App\Support;

use App\Models\Order;
use App\Models\VendorOrderMapping;
use App\Notifications\VendorNewPaidOrderNotification;
use App\Services\Vendor\VendorOrderSyncService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Notify marketplace vendors immediately when a shop order payment is confirmed.
 */
final class OrderVendorNotifier
{
    public static function notifyVendorsForPaidOrder(Order $order): void
    {
        try {
            if (! $order->isShopOrder()) {
                return;
            }

            if (strtolower((string) ($order->payment_status ?? '')) !== 'paid') {
                return;
            }

            $order = $order->fresh([
                'items.product',
                'shippingAddress',
                'user',
            ]) ?? $order;

            app(VendorOrderSyncService::class)->syncFromOrder($order);

            $mappings = VendorOrderMapping::query()
                ->with('vendor.user')
                ->where('order_id', $order->id)
                ->get();

            if ($mappings->isEmpty()) {
                return;
            }

            $hasNotifiedAt = Schema::hasColumn('vendor_order_mappings', 'vendor_notified_at');

            foreach ($mappings as $mapping) {
                if ($hasNotifiedAt && $mapping->vendor_notified_at !== null) {
                    continue;
                }

                $user = $mapping->vendor?->user;
                if ($user === null) {
                    continue;
                }

                $user->notify(new VendorNewPaidOrderNotification($order, $mapping));

                if ($hasNotifiedAt) {
                    $mapping->forceFill(['vendor_notified_at' => now()])->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Order vendor notify failed: '.$e->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }
}
