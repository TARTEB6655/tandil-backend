<?php

namespace App\Support;

use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Shared shop-order tracking timeline used by:
 * - Client GET /api/orders/{id}/track
 * - Vendor GET /api/vendor/orders/{id}/track
 *
 * Progress is driven only by the shop order's order_status
 * (supervisor claim / technician assignment / job lifecycle) — never by
 * vendor fulfillment mapping status (confirmed/shipped/etc.).
 */
final class OrderTrackingTimeline
{
    /**
     * @return list<array{key: string, label: string, description: string, completed: bool, timestamp: ?string}>
     */
    public static function forOrder(Order $order): array
    {
        if (strtolower((string) ($order->order_status ?? '')) === 'cancelled') {
            return self::cancelledTimeline($order);
        }

        return self::activeTimeline($order);
    }

    public static function statusLabel(?string $status): string
    {
        $status = self::normalize((string) ($status ?? 'pending'));
        $map = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'confirmed' => 'Confirmed',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        return $map[$status] ?? ucfirst($status !== '' ? $status : 'Pending');
    }

    public static function normalize(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'paid' => 'processing',
            'shipped' => 'completed',
            'pending', 'processing', 'confirmed', 'assigned', 'in_progress', 'completed', 'delivered', 'cancelled' => $status,
            default => $status !== '' ? $status : 'pending',
        };
    }

    public static function rank(string $status): int
    {
        return match (self::normalize($status)) {
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

    /**
     * @return list<array{key: string, label: string, description: string, completed: bool, timestamp: ?string}>
     */
    private static function activeTimeline(Order $order): array
    {
        $status = self::normalize((string) ($order->order_status ?? 'pending'));
        $rank = self::rank($status);
        $createdAt = $order->created_at;
        $updatedAt = $order->updated_at;
        $paidAt = $order->paid_at;

        return [
            self::step('pending', 'Pending', 'Order placed successfully', true, $createdAt),
            self::step(
                'processing',
                'Processing',
                'Waiting for a supervisor to accept the job',
                $rank >= self::rank('processing'),
                $rank >= self::rank('processing') ? ($paidAt ?? $updatedAt) : null
            ),
            self::step(
                'confirmed',
                'Confirmed',
                'Supervisor accepted your order',
                $rank >= self::rank('confirmed'),
                $rank >= self::rank('confirmed') ? $updatedAt : null
            ),
            self::step(
                'assigned',
                'Assigned',
                'Technician assigned to your order',
                $rank >= self::rank('assigned'),
                $rank >= self::rank('assigned') ? $updatedAt : null
            ),
            self::step(
                'in_progress',
                'In Progress',
                'Your order is being processed',
                $rank >= self::rank('in_progress'),
                $rank >= self::rank('in_progress') ? $updatedAt : null
            ),
            self::step(
                'completed',
                'Completed',
                'Your order is ready!',
                $rank >= self::rank('completed'),
                $rank >= self::rank('completed') ? $updatedAt : null
            ),
            self::step(
                'delivered',
                'Delivered',
                'Delivered',
                $rank >= self::rank('delivered'),
                $rank >= self::rank('delivered') ? $updatedAt : null
            ),
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, completed: bool, timestamp: ?string}>
     */
    private static function cancelledTimeline(Order $order): array
    {
        $createdAt = $order->created_at;
        $cancelledAt = $order->updated_at;
        $isRefunded = strtolower((string) ($order->payment_status ?? '')) === 'refunded';
        $hasRefund = (float) ($order->refund_amount ?? 0) > 0;
        $refundProcessing = $hasRefund && ! $isRefunded;

        $steps = [
            self::step('pending', 'Pending', 'Order placed successfully', true, $createdAt),
            self::step(
                'cancel_order',
                'Cancel order',
                'Order cancelled by customer request',
                true,
                $cancelledAt
            ),
        ];

        if ($refundProcessing || $isRefunded) {
            $steps[] = self::step(
                'refund_processing',
                'Refund Processing',
                'Refund request is being processed',
                $isRefunded,
                $isRefunded ? ($order->refunded_at ?? $cancelledAt) : null
            );
        }

        if ($isRefunded) {
            $steps[] = self::step(
                'refund_complete',
                'Refund complete',
                'Refund amount credited back to original payment method',
                true,
                $order->refunded_at ?? $cancelledAt
            );
        }

        return $steps;
    }

    /**
     * @return array{key: string, label: string, description: string, completed: bool, timestamp: ?string}
     */
    private static function step(
        string $key,
        string $label,
        string $description,
        bool $completed,
        mixed $at
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'completed' => $completed,
            'timestamp' => self::formatTimestamp($at),
        ];
    }

    private static function formatTimestamp(mixed $at): ?string
    {
        if ($at instanceof CarbonInterface) {
            return $at->format('g:i A');
        }

        return null;
    }
}
