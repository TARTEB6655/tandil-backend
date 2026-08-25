<?php

namespace App\Support;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\VendorOrderMapping;
use Carbon\CarbonInterface;

/**
 * Shared shop-order tracking timeline used by:
 * - Client GET /api/orders/{id}/track
 * - Vendor GET /api/vendor/orders/{id}/track
 *
 * One API; response shape follows line type (product vs service):
 * - Simple product → Pending → Confirmed → Processing → Shipped → Delivered
 * - Service → Pending → Processing → Confirmed → Assigned → In Progress → Completed → Delivered
 */
final class OrderTrackingTimeline
{
    /**
     * @return list<array{key: string, label: string, description: string, icon: string, completed: bool, current: bool, timestamp: ?string}>
     */
    public static function forOrder(Order $order): array
    {
        if (strtolower((string) ($order->order_status ?? '')) === 'cancelled') {
            return self::cancelledTimeline($order);
        }

        if (OrderFulfillmentType::usesVendorProductWorkflow($order)) {
            return self::productTimeline($order);
        }

        return self::serviceTimeline($order);
    }

    public static function fulfillmentType(Order $order): string
    {
        if (OrderFulfillmentType::usesVendorProductWorkflow($order)) {
            return OrderFulfillmentType::PRODUCT;
        }

        if (OrderFulfillmentType::hasServiceLines($order)) {
            return OrderFulfillmentType::SERVICE;
        }

        return OrderFulfillmentType::PRODUCT;
    }

    /**
     * Mobile layout hint: product = horizontal stepper, service = vertical list.
     */
    public static function trackingLayout(Order $order): string
    {
        return self::fulfillmentType($order) === OrderFulfillmentType::PRODUCT
            ? 'horizontal'
            : 'vertical';
    }

    /**
     * Primary badge status for track / order-details screens.
     *
     * @return array{status: string, status_label: string, status_icon: string}
     */
    public static function displayStatus(Order $order, ?VendorOrderMapping $mapping = null): array
    {
        if (OrderFulfillmentType::usesVendorProductWorkflow($order)) {
            $order->loadMissing('vendorMappings');
            $mapping = $mapping
                ?? $order->vendorMappings->sortByDesc('id')->first()
                ?? VendorOrderMapping::query()->where('order_id', $order->id)->latest('id')->first();

            $status = strtolower((string) ($mapping?->status ?? 'pending'));
            $enum = VendorOrderStatus::tryFrom($status) ?? VendorOrderStatus::Pending;

            return [
                'status' => $enum->value,
                'status_label' => $enum->label(),
                'status_icon' => $enum->icon(),
            ];
        }

        $status = self::normalize((string) ($order->order_status ?? 'pending'));

        return [
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'status_icon' => self::serviceIcon($status),
        ];
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
            'shipped' => 'Shipped',
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
            'pending', 'processing', 'confirmed', 'assigned', 'in_progress', 'completed', 'shipped', 'delivered', 'cancelled' => $status,
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
            'shipped' => 5,
            'delivered' => 6,
            default => 0,
        };
    }

    /**
     * Screenshot 2 — simple product horizontal stepper.
     *
     * @return list<array{key: string, label: string, description: string, icon: string, completed: bool, current: bool, timestamp: ?string}>
     */
    private static function productTimeline(Order $order): array
    {
        $order->loadMissing(['vendorMappings', 'items.product']);
        $mapping = $order->vendorMappings->sortByDesc('id')->first()
            ?? VendorOrderMapping::query()->where('order_id', $order->id)->latest('id')->first();

        $vendorStatus = strtolower((string) ($mapping?->status ?? VendorOrderStatus::Pending->value));
        $currentKey = match ($vendorStatus) {
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'processing' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            default => 'pending',
        };

        $rank = match ($vendorStatus) {
            'pending' => 0,
            'confirmed' => 1,
            'processing' => 2,
            'shipped' => 3,
            'delivered' => 4,
            'cancelled' => -1,
            default => 0,
        };

        $createdAt = $order->created_at;
        $updatedAt = $mapping?->updated_at ?? $order->updated_at;

        $steps = [
            ['key' => 'pending', 'label' => 'Pending', 'description' => 'Order placed successfully', 'icon' => 'clock', 'min' => 0],
            ['key' => 'confirmed', 'label' => 'Confirmed', 'description' => 'Vendor confirmed your order', 'icon' => 'check', 'min' => 1],
            ['key' => 'processing', 'label' => 'Processing', 'description' => 'Vendor is preparing your order', 'icon' => 'package', 'min' => 2],
            ['key' => 'shipped', 'label' => 'Shipped', 'description' => 'Out for delivery — give the OTP to the vendor on arrival', 'icon' => 'truck', 'min' => 3],
            ['key' => 'delivered', 'label' => 'Delivered', 'description' => 'Delivery confirmed with OTP', 'icon' => 'check-double', 'min' => 4],
        ];

        $out = [];
        foreach ($steps as $step) {
            $completed = $rank >= $step['min'];
            $at = match ($step['key']) {
                'pending' => $createdAt,
                'delivered' => $mapping?->delivery_otp_confirmed_at ?? ($completed ? $updatedAt : null),
                default => $completed ? $updatedAt : null,
            };
            if ($step['key'] === 'pending') {
                $at = $createdAt;
            }

            $out[] = self::step(
                $step['key'],
                $step['label'],
                $step['description'],
                $step['icon'],
                $completed,
                $step['key'] === $currentKey,
                $at
            );
        }

        return $out;
    }

    /**
     * Screenshot 1 — service vertical timeline.
     *
     * @return list<array{key: string, label: string, description: string, icon: string, completed: bool, current: bool, timestamp: ?string}>
     */
    private static function serviceTimeline(Order $order): array
    {
        $order->loadMissing('items.product');
        $status = self::normalize((string) ($order->order_status ?? 'pending'));
        $rank = self::rank($status);
        $createdAt = $order->created_at;
        $updatedAt = $order->updated_at;
        $paidAt = $order->paid_at;
        $productLabel = self::primaryProductLabel($order);

        $steps = [
            ['key' => 'pending', 'label' => 'Pending', 'description' => 'Order placed successfully', 'icon' => 'clock', 'min' => 0],
            ['key' => 'processing', 'label' => 'Processing', 'description' => 'Waiting for a supervisor to accept the job', 'icon' => 'gear', 'min' => 1],
            ['key' => 'confirmed', 'label' => 'Confirmed', 'description' => 'Order confirmed by our team', 'icon' => 'check', 'min' => 2],
            ['key' => 'assigned', 'label' => 'Assigned', 'description' => 'Technician assigned to your order', 'icon' => 'user', 'min' => 3],
            ['key' => 'in_progress', 'label' => 'In Progress', 'description' => 'Technician is working on your '.$productLabel, 'icon' => 'wrench', 'min' => 4],
            ['key' => 'completed', 'label' => 'Completed', 'description' => 'Your order is ready!', 'icon' => 'check', 'min' => 5],
            ['key' => 'delivered', 'label' => 'Delivered', 'description' => 'Delivered', 'icon' => 'check-circle', 'min' => 6],
        ];

        $out = [];
        foreach ($steps as $step) {
            $completed = $rank >= $step['min'];
            $isCurrent = $status === $step['key'] || ($status === 'paid' && $step['key'] === 'processing');
            $at = match ($step['key']) {
                'pending' => $createdAt,
                'processing' => $completed ? ($paidAt ?? $updatedAt) : null,
                default => $completed ? $updatedAt : null,
            };

            $out[] = self::step(
                $step['key'],
                $step['label'],
                $step['description'],
                $step['icon'],
                $completed,
                $isCurrent && $completed,
                $at
            );
        }

        // Ensure exactly one current step (highest completed).
        $currentSet = false;
        for ($i = count($out) - 1; $i >= 0; $i--) {
            if ($out[$i]['completed'] && ! $currentSet) {
                $out[$i]['current'] = true;
                $currentSet = true;
            } else {
                $out[$i]['current'] = false;
            }
        }
        if (! $currentSet && $out !== []) {
            $out[0]['current'] = true;
        }

        return $out;
    }

    /**
     * @return list<array{key: string, label: string, description: string, icon: string, completed: bool, current: bool, timestamp: ?string}>
     */
    private static function cancelledTimeline(Order $order): array
    {
        $createdAt = $order->created_at;
        $cancelledAt = $order->updated_at;
        $isRefunded = strtolower((string) ($order->payment_status ?? '')) === 'refunded';
        $hasRefund = (float) ($order->refund_amount ?? 0) > 0;
        $refundProcessing = $hasRefund && ! $isRefunded;

        $steps = [
            self::step('pending', 'Pending', 'Order placed successfully', 'clock', true, false, $createdAt),
            self::step('cancel_order', 'Cancel order', 'Order cancelled by customer request', 'x', true, ! $refundProcessing && ! $isRefunded, $cancelledAt),
        ];

        if ($refundProcessing || $isRefunded) {
            $steps[] = self::step(
                'refund_processing',
                'Refund Processing',
                'Refund request is being processed',
                'clock',
                $isRefunded,
                $refundProcessing && ! $isRefunded,
                $isRefunded ? ($order->refunded_at ?? $cancelledAt) : null
            );
        }

        if ($isRefunded) {
            $steps[] = self::step(
                'refund_complete',
                'Refund complete',
                'Refund amount credited back to original payment method',
                'check-circle',
                true,
                true,
                $order->refunded_at ?? $cancelledAt
            );
        }

        return $steps;
    }

    /**
     * @return array{key: string, label: string, description: string, icon: string, completed: bool, current: bool, timestamp: ?string}
     */
    private static function step(
        string $key,
        string $label,
        string $description,
        string $icon,
        bool $completed,
        bool $current,
        mixed $at
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'icon' => $icon,
            'completed' => $completed,
            'current' => $current,
            'timestamp' => self::formatTimestamp($at),
        ];
    }

    private static function primaryProductLabel(Order $order): string
    {
        $name = trim((string) ($order->items->first()?->product?->name ?? ''));
        if ($name === '') {
            return 'order';
        }

        return mb_strtolower($name);
    }

    private static function serviceIcon(string $status): string
    {
        return match (self::normalize($status)) {
            'pending' => 'clock',
            'processing' => 'gear',
            'confirmed' => 'check',
            'assigned' => 'user',
            'in_progress' => 'wrench',
            'completed' => 'check',
            'delivered' => 'check-circle',
            'cancelled' => 'x',
            default => 'clock',
        };
    }

    private static function formatTimestamp(mixed $at): ?string
    {
        if ($at instanceof CarbonInterface) {
            return $at->format('g:i A');
        }

        return null;
    }
}
