<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Visit;

final class VisitOrderTrackingSync
{
    /**
     * Sync shop order status from linked visit lifecycle (if visit notes include [SHOP-ORDER:id]).
     */
    public static function syncFromVisit(Visit $visit): void
    {
        $orderId = $visit->order_id ? (int) $visit->order_id : self::extractOrderIdFromNotes((string) ($visit->notes ?? ''));
        if (! $orderId) {
            return;
        }

        $order = Order::query()->find($orderId);
        if (! $order) {
            return;
        }
        if (in_array((string) $order->order_status, ['cancelled', 'delivered'], true)) {
            return;
        }

        $target = self::targetStatusForVisit($visit);
        if ($target === null) {
            return;
        }

        $current = (string) ($order->order_status ?? 'pending');
        if (self::rank($target) <= self::rank($current)) {
            return;
        }

        $order->order_status = $target;
        $order->save();
    }

    private static function extractOrderIdFromNotes(string $notes): ?int
    {
        if (! preg_match('/\[SHOP-ORDER:(\d+)\]/', $notes, $m)) {
            return null;
        }
        $id = (int) ($m[1] ?? 0);

        return $id > 0 ? $id : null;
    }

    private static function targetStatusForVisit(Visit $visit): ?string
    {
        $status = (string) ($visit->status ?? 'pending');
        if ($status === 'completed') {
            $visit->loadMissing('report');
            if (($visit->report?->status ?? null) === 'sent_to_client') {
                return 'completed';
            }
        }
        if ($status === 'in_progress') {
            return 'in_progress';
        }
        if ($visit->technician_id !== null || $status === 'pending_acceptance') {
            return 'assigned';
        }
        // Confirmed only after a supervisor claims the job (clicks Accept).
        if ($visit->supervisor_id !== null) {
            return 'confirmed';
        }
        // Job routed to an area pool — waiting for any area supervisor to claim.
        if ($visit->area_id !== null) {
            return 'processing';
        }

        return 'pending';
    }

    private static function rank(string $status): int
    {
        return match ($status) {
            'pending' => 0,
            'processing' => 1,
            'confirmed' => 2,
            'assigned' => 3,
            'in_progress' => 4,
            'completed' => 5,
            'delivered' => 6,
            default => 0,
        };
    }
}
