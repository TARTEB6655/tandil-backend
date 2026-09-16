<?php

namespace App\Services;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\VendorOrderMapping;
use App\Models\Visit;
use App\Support\OrderFulfillmentType;
use App\Support\ShopBookingSlotHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shop product lines (vendor + platform) that do not create service visits
 * still appear on the admin jobs calendar — including old delivered orders.
 */
class JobCalendarService
{
    /**
     * Product/platform order lines through the calendar end date (no lower bound),
     * so historical delivered products always appear when viewing "up to now".
     *
     * @return Collection<int, array{
     *     order: Order,
     *     item: OrderItem,
     *     mapping: VendorOrderMapping|null,
     *     fulfillment_type: string,
     *     scheduled_date: string,
     *     scheduled_time: string|null,
     *     duration_minutes: int|null
     * }>
     */
    public function shopOrderEntries(Carbon $from, Carbon $to): Collection
    {
        // Keep $from for signature compatibility; product rows use "up to $to" only.
        unset($from);

        $toStr = $to->toDateString();

        // Items that already have a visit are shown via the visit payload (slot backfill, etc.).
        $visitedItemIds = Visit::query()
            ->whereNotNull('order_item_id')
            ->pluck('order_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $orders = Order::query()
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                    ->orWhere('order_status', 'delivered')
                    ->orWhereHas('vendorMappings', fn ($vm) => $vm->where('status', VendorOrderStatus::Delivered->value));
            })
            ->whereHas('items')
            ->with([
                'items.product.services',
                'user:id,name',
                'vendorMappings',
            ])
            ->get();

        $entries = collect();

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (in_array((int) $item->id, $visitedItemIds, true)) {
                    continue;
                }

                $fulfillment = OrderFulfillmentType::forOrderItem($item);
                if ($fulfillment === OrderFulfillmentType::SERVICE) {
                    continue;
                }

                [$scheduledDate, $scheduledTime, $durationMinutes] = $this->resolveSchedule($order, $item);
                // Everything up to the selected end date (old delivered included).
                if ($scheduledDate === null || $scheduledDate > $toStr) {
                    continue;
                }

                $mapping = null;
                if ($fulfillment === OrderFulfillmentType::PRODUCT) {
                    $vendorId = (int) ($item->product?->vendor_id ?? 0);
                    $mapping = $order->vendorMappings->first(
                        fn (VendorOrderMapping $m) => $vendorId <= 0 || (int) $m->vendor_id === $vendorId
                    );
                }

                $entries->push([
                    'order' => $order,
                    'item' => $item,
                    'mapping' => $mapping,
                    'fulfillment_type' => $fulfillment,
                    'scheduled_date' => $scheduledDate,
                    'scheduled_time' => $scheduledTime,
                    'duration_minutes' => $durationMinutes,
                ]);
            }
        }

        return $entries
            ->sortBy([
                ['scheduled_date', 'asc'],
                fn (array $row) => $row['scheduled_time'] ?? '99:99',
            ])
            ->values();
    }

    /**
     * Prefer delivery timestamp for delivered products so they land on the day
     * they were actually delivered; otherwise booking → paid → created.
     *
     * @return array{0: ?string, 1: ?string, 2: ?int}
     */
    private function resolveSchedule(Order $order, OrderItem $item): array
    {
        $mapping = $order->relationLoaded('vendorMappings')
            ? $order->vendorMappings->first(
                fn (VendorOrderMapping $m) => (int) $m->vendor_id === (int) ($item->product?->vendor_id ?? 0)
                    || (int) ($item->product?->vendor_id ?? 0) <= 0
            )
            : null;

        $orderStatus = strtolower(trim((string) ($order->order_status ?? '')));
        $vendorStatus = strtolower(trim((string) ($mapping?->status ?? '')));
        $isDelivered = $orderStatus === 'delivered' || $vendorStatus === 'delivered';

        $bookingDate = null;

        // Delivered products: prefer OTP confirm day so they show on the real delivery date.
        if ($isDelivered && $mapping?->delivery_otp_confirmed_at) {
            $bookingDate = Carbon::parse($mapping->delivery_otp_confirmed_at)->toDateString();
        }

        if ($bookingDate === null) {
            $bookingDate = ShopBookingSlotHelper::normalizedDate(
                $item->booking_date?->format('Y-m-d') ?? (is_string($item->booking_date) ? $item->booking_date : null)
            ) ?? ShopBookingSlotHelper::normalizedDate(
                $order->booking_date?->format('Y-m-d') ?? (is_string($order->booking_date) ? $order->booking_date : null)
            );
        }

        if ($bookingDate === null) {
            $fallback = $order->paid_at ?? $order->created_at;
            $bookingDate = $fallback ? Carbon::parse($fallback)->toDateString() : null;
        }

        $bookingSlot = ShopBookingSlotHelper::normalizedSlot($item->booking_slot)
            ?? ShopBookingSlotHelper::normalizedSlot($order->booking_slot);

        $parsed = ShopBookingSlotHelper::parseSlotRange($bookingSlot);

        return [$bookingDate, $parsed['start'], $parsed['duration_minutes']];
    }
}
