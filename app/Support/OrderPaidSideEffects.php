<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Facades\Log;

/**
 * Single entry-point after a shop order becomes paid.
 *
 * Wave 1 (same for platform + vendor products):
 * 1) Admin alert (optional)
 * 2) Create supervisor job visits (moves order_status → processing when area resolves)
 * 3) Supervisor + area-manager alerts
 * 4) Vendor paid-order notifications (full order details)
 */
final class OrderPaidSideEffects
{
    public static function run(Order $order, string $placedBy, bool $notifyAdmins = true): void
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

            $total = (float) $order->total_amount;

            if ($notifyAdmins) {
                self::notifyAdmins($order, $total, $placedBy);
            }

            // Visits first so VisitOrderTrackingSync can set order_status=processing
            // before vendor/list/track consumers read the order.
            OrderToVisitDispatcher::createVisitsForPaidOrder($order);
            $order = $order->fresh([
                'items.product',
                'shippingAddress',
                'user',
            ]) ?? $order;

            self::ensureProcessingStatus($order);

            OrderSupervisorNotifier::notifySupervisorsForPaidOrder($order, $total, $placedBy);
            OrderVendorNotifier::notifyVendorsForPaidOrder($order);
        } catch (\Throwable $e) {
            Log::warning('Order paid side-effects failed: '.$e->getMessage(), [
                'order_id' => $order->id ?? null,
            ]);
        }
    }

    private static function notifyAdmins(Order $order, float $total, string $placedBy): void
    {
        try {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'New Order Received',
                    "A new order #{$order->id} has been placed by {$placedBy} for AED {$total}."
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Admin paid-order notify failed: '.$e->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }

    /**
     * If no visit was created (no matching area), still move paid shop orders
     * out of bare pending/paid so list + track show Processing.
     */
    private static function ensureProcessingStatus(Order $order): void
    {
        $status = strtolower(trim((string) ($order->order_status ?? 'pending')));
        if (in_array($status, ['pending', 'paid', ''], true)) {
            $order->order_status = 'processing';
            $order->save();
        }
    }
}
