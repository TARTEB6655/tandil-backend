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
 * Shop product/platform lines on the admin jobs calendar.
 *
 * Date filter matches visits: only rows with scheduled_date in [from, to].
 * Day view for Aug 28 must NOT reuse Aug 26 product rows.
 */
class JobCalendarService
{
    /**
     * @return Collection<int, array{
     *     order: Order,
     *     item: OrderItem|null,
     *     mapping: VendorOrderMapping|null,
     *     fulfillment_type: string,
     *     scheduled_date: string,
     *     scheduled_time: string|null,
     *     duration_minutes: int|null,
     *     title: string
     * }>
     */
    public function shopOrderEntries(Carbon $from, Carbon $to): Collection
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $visitedInWindowItemIds = Visit::query()
            ->whereNotNull('order_item_id')
            ->whereDate('scheduled_date', '>=', $fromStr)
            ->whereDate('scheduled_date', '<=', $toStr)
            ->pluck('order_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $orders = Order::query()
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                    ->orWhere('order_status', 'delivered')
                    ->orWhereHas('vendorMappings');
            })
            ->where(function ($q) {
                $q->whereHas('items')->orWhereHas('vendorMappings');
            })
            ->with([
                'items.product.services',
                'user:id,name',
                'vendorMappings',
            ])
            ->get();

        $entries = collect();
        $coveredMappingIds = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (in_array((int) $item->id, $visitedInWindowItemIds, true)) {
                    continue;
                }

                $mapping = $this->vendorMappingForItem($order, $item);
                $fulfillment = OrderFulfillmentType::forOrderItem($item);

                if ($fulfillment === OrderFulfillmentType::SERVICE && $mapping === null) {
                    continue;
                }

                $displayType = $fulfillment === OrderFulfillmentType::SERVICE
                    ? OrderFulfillmentType::PRODUCT
                    : $fulfillment;

                [$scheduledDate, $scheduledTime, $durationMinutes] = $this->resolveSchedule(
                    $order,
                    $item,
                    $mapping
                );

                // Strict calendar window (day/week/month) — same as visits.
                if ($scheduledDate === null || $scheduledDate < $fromStr || $scheduledDate > $toStr) {
                    continue;
                }

                if ($mapping) {
                    $coveredMappingIds[(int) $mapping->id] = true;
                }

                $entries->push([
                    'order' => $order,
                    'item' => $item,
                    'mapping' => $mapping,
                    'fulfillment_type' => $displayType,
                    'scheduled_date' => $scheduledDate,
                    'scheduled_time' => $scheduledTime,
                    'duration_minutes' => $durationMinutes,
                    'title' => $this->resolveProductTitle($order, $item, $mapping),
                ]);
            }

            foreach ($order->vendorMappings as $mapping) {
                if (isset($coveredMappingIds[(int) $mapping->id])) {
                    continue;
                }

                [$scheduledDate, $scheduledTime, $durationMinutes] = $this->resolveSchedule(
                    $order,
                    $this->bestItemForMapping($order, $mapping),
                    $mapping
                );

                if ($scheduledDate === null || $scheduledDate < $fromStr || $scheduledDate > $toStr) {
                    continue;
                }

                $coveredMappingIds[(int) $mapping->id] = true;
                $item = $this->bestItemForMapping($order, $mapping);

                $entries->push([
                    'order' => $order,
                    'item' => $item,
                    'mapping' => $mapping,
                    'fulfillment_type' => OrderFulfillmentType::PRODUCT,
                    'scheduled_date' => $scheduledDate,
                    'scheduled_time' => $scheduledTime,
                    'duration_minutes' => $durationMinutes,
                    'title' => $this->resolveProductTitle($order, $item, $mapping),
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

    private function vendorMappingForItem(Order $order, OrderItem $item): ?VendorOrderMapping
    {
        $order->loadMissing('vendorMappings');
        $vendorId = (int) ($item->product?->vendor_id ?? 0);

        if ($vendorId > 0) {
            $match = $order->vendorMappings->first(
                fn (VendorOrderMapping $m) => (int) $m->vendor_id === $vendorId
            );
            if ($match) {
                return $match;
            }
        }

        return $order->vendorMappings->count() === 1
            ? $order->vendorMappings->first()
            : null;
    }

    private function bestItemForMapping(Order $order, VendorOrderMapping $mapping): ?OrderItem
    {
        $order->loadMissing('items.product');

        $matched = $order->items->first(
            fn (OrderItem $item) => (int) ($item->product?->vendor_id ?? 0) === (int) $mapping->vendor_id
        );

        return $matched ?? $order->items->first();
    }

    /**
     * Prefer real catalog name; avoid generic "Product" when order_number is known.
     */
    private function resolveProductTitle(Order $order, ?OrderItem $item, ?VendorOrderMapping $mapping): string
    {
        $name = trim((string) ($item?->product?->name ?? ''));
        if ($name !== '' && strtolower($name) !== 'product') {
            return $name;
        }

        // Try any other line on the order with a real name.
        $order->loadMissing('items.product');
        foreach ($order->items as $line) {
            $candidate = trim((string) ($line->product?->name ?? ''));
            if ($candidate !== '' && strtolower($candidate) !== 'product') {
                return $candidate;
            }
        }

        if ($name !== '') {
            return $name;
        }

        return $order->publicOrderNumber();
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?int}
     */
    private function resolveSchedule(Order $order, ?OrderItem $item, ?VendorOrderMapping $mapping): array
    {
        $orderStatus = strtolower(trim((string) ($order->order_status ?? '')));
        $vendorStatus = strtolower(trim((string) ($mapping?->status ?? '')));
        $isDelivered = $orderStatus === 'delivered' || $vendorStatus === 'delivered';

        $bookingDate = null;

        if ($isDelivered && $mapping?->delivery_otp_confirmed_at) {
            $bookingDate = Carbon::parse($mapping->delivery_otp_confirmed_at)->toDateString();
        }

        if ($bookingDate === null && $item) {
            $itemDate = $item->booking_date;
            $bookingDate = ShopBookingSlotHelper::normalizedDate(
                $itemDate instanceof \DateTimeInterface
                    ? $itemDate->format('Y-m-d')
                    : (is_string($itemDate) ? $itemDate : null)
            );
        }

        if ($bookingDate === null) {
            $orderDate = $order->booking_date;
            $bookingDate = ShopBookingSlotHelper::normalizedDate(
                $orderDate instanceof \DateTimeInterface
                    ? $orderDate->format('Y-m-d')
                    : (is_string($orderDate) ? $orderDate : null)
            );
        }

        if ($bookingDate === null) {
            $fallback = $mapping?->delivery_otp_confirmed_at
                ?? $order->paid_at
                ?? $order->created_at;
            $bookingDate = $fallback ? Carbon::parse($fallback)->toDateString() : null;
        }

        $bookingSlot = ShopBookingSlotHelper::normalizedSlot($item?->booking_slot)
            ?? ShopBookingSlotHelper::normalizedSlot($order->booking_slot);

        $parsed = ShopBookingSlotHelper::parseSlotRange($bookingSlot);

        return [$bookingDate, $parsed['start'], $parsed['duration_minutes']];
    }
}
