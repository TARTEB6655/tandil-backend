<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin dashboard order statistics align with platform + marketplace (vendor) shop statuses.
 *
 * Vendor fulfillment mirrors mapping status onto orders.order_status (confirmed, shipped, …).
 */
final class AdminOrderStatusBuckets
{
    /** @var list<string> */
    public const PENDING = ['pending'];

    /** @var list<string> */
    public const ASSIGNED = ['assigned'];

    /** @var list<string> */
    public const IN_PROGRESS = ['processing', 'in_progress', 'confirmed', 'shipped'];

    /** @var list<string> */
    public const COMPLETED = ['completed', 'delivered'];

    /** @var list<string> */
    public const CANCELLED = ['cancelled'];

    /**
     * @param  Builder<Order>  $query
     * @param  list<string>  $statuses
     * @return Builder<Order>
     */
    public static function whereStatusIn(Builder $query, array $statuses): Builder
    {
        return $query->whereIn('order_status', $statuses);
    }

    public static function pendingCount(): int
    {
        return Order::whereIn('order_status', self::PENDING)->count();
    }

    public static function assignedCount(): int
    {
        return Order::whereIn('order_status', self::ASSIGNED)->count();
    }

    public static function inProgressCount(): int
    {
        return Order::whereIn('order_status', self::IN_PROGRESS)->count();
    }

    public static function completedCount(): int
    {
        return Order::whereIn('order_status', self::COMPLETED)->count();
    }

    public static function cancelledCount(): int
    {
        return Order::whereIn('order_status', self::CANCELLED)->count();
    }

    /**
     * @return array<string, mixed>
     */
    public static function orderStatisticsSummary(): array
    {
        return [
            'pending_orders' => self::pendingCount(),
            'assigned_orders' => self::assignedCount(),
            'in_progress_orders' => self::inProgressCount(),
            'completed_orders' => self::completedCount(),
            'cancelled_orders' => self::cancelledCount(),
        ];
    }
}
