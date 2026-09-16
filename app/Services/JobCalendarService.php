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
 * including historical delivered orders.
 *
 * If a line already has a visit inside the selected window, the visit row
 * is shown. If the only visit is outside the window (legacy), the product
 * still appears here as shop_order — otherwise it vanished from day view.
 */
class JobCalendarService
{
    /**
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

        // Visits already painted in this calendar window — don't duplicate as shop_order.
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
                    ->orWhereHas(
                        'vendorMappings',
                        fn ($vm) => $vm->where('status', VendorOrderStatus::Delivered->value)
                    );
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
                if (in_array((int) $item->id, $visitedInWindowItemIds, true)) {
                    continue;
                }

                $mapping = $this->vendorMappingForItem($order, $item);
                $fulfillment = OrderFulfillmentType::forOrderItem($item);

                // Pure supervisor services (no vendor mapping) belong on the visit path only.
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

                // Include full history through the selected end date.
                if ($scheduledDate === null || $scheduledDate > $toStr) {
                    continue;
                }

                $entries->push([
                    'order' => $order,
                    'item' => $item,
                    'mapping' => $mapping,
                    'fulfillment_type' => $displayType,
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

    private function vendorMappingForItem(Order $order, OrderItem $item): ?VendorOrderMapping
    {
        $order->loadMissing('vendorMappings');
        $vendorId = (int) ($item->product?->vendor_id ?? 0);

        if ($vendorId > 0) {
            return $order->vendorMappings->first(
                fn (VendorOrderMapping $m) => (int) $m->vendor_id === $vendorId
            );
        }

        return $order->vendorMappings->count() === 1
            ? $order->vendorMappings->first()
            : null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?int}
     */
    private function resolveSchedule(Order $order, OrderItem $item, ?VendorOrderMapping $mapping): array
    {
        $orderStatus = strtolower(trim((string) ($order->order_status ?? '')));
        $vendorStatus = strtolower(trim((string) ($mapping?->status ?? '')));
        $isDelivered = $orderStatus === 'delivered' || $vendorStatus === 'delivered';

        $bookingDate = null;

        if ($isDelivered && $mapping?->delivery_otp_confirmed_at) {
            $bookingDate = Carbon::parse($mapping->delivery_otp_confirmed_at)->toDateString();
        }

        if ($bookingDate === null) {
            $itemDate = $item->booking_date;
            $orderDate = $order->booking_date;
            $bookingDate = ShopBookingSlotHelper::normalizedDate(
                $itemDate instanceof \DateTimeInterface
                    ? $itemDate->format('Y-m-d')
                    : (is_string($itemDate) ? $itemDate : null)
            ) ?? ShopBookingSlotHelper::normalizedDate(
                $orderDate instanceof \DateTimeInterface
                    ? $orderDate->format('Y-m-d')
                    : (is_string($orderDate) ? $orderDate : null)
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
