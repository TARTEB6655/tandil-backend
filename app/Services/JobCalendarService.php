<?php

namespace App\Services;

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
 * still appear on the admin jobs calendar.
 */
class JobCalendarService
{
    /**
     * Paid product/platform order lines without a linked visit, scheduled in range.
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
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $visitedItemIds = Visit::query()
            ->whereNotNull('order_item_id')
            ->pluck('order_item_id')
            ->all();

        $wholeOrderVisitOrderIds = Visit::query()
            ->whereNotNull('order_id')
            ->whereNull('order_item_id')
            ->pluck('order_id')
            ->all();

        $orders = Order::query()
            ->where('payment_status', 'paid')
            ->whereHas('items.product')
            ->with([
                'items.product.services',
                'user:id,name',
                'vendorMappings',
            ])
            ->get();

        $entries = collect();

        foreach ($orders as $order) {
            if (in_array((int) $order->id, array_map('intval', $wholeOrderVisitOrderIds), true)) {
                continue;
            }

            foreach ($order->items as $item) {
                if (in_array((int) $item->id, array_map('intval', $visitedItemIds), true)) {
                    continue;
                }

                $fulfillment = OrderFulfillmentType::forOrderItem($item);
                if ($fulfillment === OrderFulfillmentType::SERVICE) {
                    continue;
                }

                [$scheduledDate, $scheduledTime, $durationMinutes] = $this->resolveSchedule($order, $item);
                if ($scheduledDate === null || $scheduledDate < $fromStr || $scheduledDate > $toStr) {
                    continue;
                }

                $mapping = null;
                if ($fulfillment === OrderFulfillmentType::PRODUCT) {
                    $vendorId = (int) ($item->product?->vendor_id ?? 0);
                    $mapping = $order->vendorMappings->first(
                        fn (VendorOrderMapping $m) => (int) $m->vendor_id === $vendorId
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
     * @return array{0: ?string, 1: ?string, 2: ?int}
     */
    private function resolveSchedule(Order $order, OrderItem $item): array
    {
        $bookingDate = ShopBookingSlotHelper::normalizedDate(
            $item->booking_date?->format('Y-m-d') ?? (is_string($item->booking_date) ? $item->booking_date : null)
        ) ?? ShopBookingSlotHelper::normalizedDate(
            $order->booking_date?->format('Y-m-d') ?? (is_string($order->booking_date) ? $order->booking_date : null)
        );

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
