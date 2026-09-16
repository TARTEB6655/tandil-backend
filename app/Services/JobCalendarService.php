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
 * Shop product lines (vendor + platform) on the admin jobs calendar,
 * including historical delivered vendor orders (e.g. order_0061 / order_0067).
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

                if ($scheduledDate === null || $scheduledDate > $toStr) {
                    continue;
                }

                if ($mapping) {
                    $coveredMappingIds[(int) $mapping->id] = true;
                }

                $productName = trim((string) ($item->product?->name ?? ''));

                $entries->push([
                    'order' => $order,
                    'item' => $item,
                    'mapping' => $mapping,
                    'fulfillment_type' => $displayType,
                    'scheduled_date' => $scheduledDate,
                    'scheduled_time' => $scheduledTime,
                    'duration_minutes' => $durationMinutes,
                    'title' => $productName !== '' ? $productName : 'Product',
                ]);
            }

            // Vendor mappings with no matching line items (UI shows "Product" Qty 0)
            // must still appear on the calendar — same as Delivered Orders list.
            foreach ($order->vendorMappings as $mapping) {
                if (isset($coveredMappingIds[(int) $mapping->id])) {
                    continue;
                }

                [$scheduledDate, $scheduledTime, $durationMinutes] = $this->resolveSchedule(
                    $order,
                    $order->items->first(),
                    $mapping
                );

                if ($scheduledDate === null || $scheduledDate > $toStr) {
                    continue;
                }

                $coveredMappingIds[(int) $mapping->id] = true;

                $entries->push([
                    'order' => $order,
                    'item' => null,
                    'mapping' => $mapping,
                    'fulfillment_type' => OrderFulfillmentType::PRODUCT,
                    'scheduled_date' => $scheduledDate,
                    'scheduled_time' => $scheduledTime,
                    'duration_minutes' => $durationMinutes,
                    'title' => 'Product',
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

        // Screenshot case: product.vendor_id no longer matches mapping, but order
        // still has a single vendor mapping (Delivered Orders still lists it).
        return $order->vendorMappings->count() === 1
            ? $order->vendorMappings->first()
            : null;
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
