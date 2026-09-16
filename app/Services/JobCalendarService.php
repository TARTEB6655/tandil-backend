<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\VendorOrderMapping;
use App\Models\Visit;
use App\Support\OrderFulfillmentType;
use App\Support\ShopBookingSlotHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shop product/platform lines on the admin jobs calendar.
 *
 * Service lines stay on the visit path only (avoids duplicate cards).
 * Date filter matches visits: scheduled_date in [from, to].
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

        // Any linked service visit means the job is the visit card — never also shop_order.
        $serviceVisitOrderIds = Visit::query()
            ->where(function ($q) {
                $q->whereNotNull('order_id')
                    ->orWhereNotNull('order_item_id');
            })
            ->with(['orderItem.product.services'])
            ->get()
            ->filter(function (Visit $visit) {
                $item = $visit->orderItem;
                if (! $item) {
                    // Notes-linked service visits (no order_item_id) still suppress mapping cards.
                    $notes = (string) ($visit->notes ?? '');

                    return (int) ($visit->order_id ?? 0) > 0
                        && stripos($notes, 'Order Service Visit') !== false;
                }

                return OrderFulfillmentType::forOrderItem($item) === OrderFulfillmentType::SERVICE;
            })
            ->map(fn (Visit $v) => (int) ($v->order_id ?? $v->orderItem?->order_id ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
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
            $order->setRelation(
                'items',
                OrderItem::query()
                    ->with('product.services')
                    ->where('order_id', $order->id)
                    ->orderBy('id')
                    ->get()
            );

            $hasServiceVisitInWindow = in_array((int) $order->id, $serviceVisitOrderIds, true);

            foreach ($order->items as $item) {
                $fulfillment = OrderFulfillmentType::forOrderItem($item);

                // True service lines are rendered via visits. Mis-tagged service SKUs
                // without a visit in-window are still covered by the mapping loop below.
                if ($fulfillment === OrderFulfillmentType::SERVICE) {
                    continue;
                }

                $mapping = $this->vendorMappingForItem($order, $item);

                [$scheduledDate, $scheduledTime, $durationMinutes] = $this->resolveSchedule(
                    $order,
                    $item,
                    $mapping
                );

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
                    'fulfillment_type' => $fulfillment,
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

                // Avoid duplicate shop_order when a service visit already represents this order.
                if ($hasServiceVisitInWindow) {
                    continue;
                }

                $item = $this->bestItemForMapping($order, $mapping);

                [$scheduledDate, $scheduledTime, $durationMinutes] = $this->resolveSchedule(
                    $order,
                    $item,
                    $mapping
                );

                if ($scheduledDate === null || $scheduledDate < $fromStr || $scheduledDate > $toStr) {
                    continue;
                }

                $coveredMappingIds[(int) $mapping->id] = true;

                $fulfillment = $item
                    ? (OrderFulfillmentType::forOrderItem($item) === OrderFulfillmentType::SERVICE
                        ? OrderFulfillmentType::PRODUCT
                        : OrderFulfillmentType::forOrderItem($item))
                    : OrderFulfillmentType::PRODUCT;

                $entries->push([
                    'order' => $order,
                    'item' => $item,
                    'mapping' => $mapping,
                    'fulfillment_type' => $fulfillment,
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
        $items = OrderItem::query()
            ->with('product.services')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return $this->itemFromVisitNotes($order);
        }

        $matched = $items->first(
            fn (OrderItem $item) => (int) ($item->product?->vendor_id ?? 0) === (int) $mapping->vendor_id
        );
        if ($matched) {
            return $matched;
        }

        // Prefer non-service catalog lines for product calendar cards.
        $productLine = $items->first(
            fn (OrderItem $item) => OrderFulfillmentType::forOrderItem($item) !== OrderFulfillmentType::SERVICE
        );

        return $productLine
            ?? $items->first(fn (OrderItem $item) => $item->product !== null || (int) ($item->product_id ?? 0) > 0)
            ?? $items->first();
    }

    private function itemFromVisitNotes(Order $order): ?OrderItem
    {
        $visits = Visit::query()
            ->where(function ($q) use ($order) {
                $q->where('order_id', $order->id)
                    ->orWhere('notes', 'like', '%[SHOP-ORDER:'.$order->id.']%')
                    ->orWhere('notes', 'like', '%Order #'.$order->id.'%');
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'order_item_id', 'notes']);

        foreach ($visits as $visit) {
            $itemId = (int) ($visit->order_item_id ?? 0);
            if ($itemId <= 0 && is_string($visit->notes) && preg_match('/\[ITEM:(\d+)\]/', $visit->notes, $m)) {
                $itemId = (int) $m[1];
            }
            if ($itemId <= 0) {
                continue;
            }
            $item = OrderItem::query()->with('product.services')->find($itemId);
            if ($item) {
                return $item;
            }
        }

        return null;
    }

    private function resolveProductTitle(Order $order, ?OrderItem $item, ?VendorOrderMapping $mapping): string
    {
        $candidates = [];

        $push = function (?string $value) use (&$candidates): void {
            $name = trim((string) $value);
            if ($name === '' || preg_match('/^order_\d+$/i', $name)) {
                return;
            }
            $candidates[] = $name;
        };

        $push($item?->product_name);
        $push($mapping?->product_title);

        $items = OrderItem::query()
            ->with('product')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        if ($item && ! $items->contains('id', $item->id)) {
            $items->prepend($item);
        }

        foreach ($items as $line) {
            $line->loadMissing('product');
            $push($line->product_name);
            $push($line->product?->name);
            $productId = (int) ($line->product_id ?? 0);
            if ($productId > 0 && ! $line->product) {
                $push(Product::query()->whereKey($productId)->value('name'));
            }
        }

        $itemIds = $items->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $visitNotes = Visit::query()
            ->where(function ($q) use ($order, $itemIds) {
                $q->where('order_id', $order->id);
                if ($itemIds !== []) {
                    $q->orWhereIn('order_item_id', $itemIds);
                }
                $q->orWhere('notes', 'like', '%[SHOP-ORDER:'.$order->id.']%')
                    ->orWhere('notes', 'like', '%Order #'.$order->id.'%')
                    ->orWhere('notes', 'like', '%Order # '.$order->id.'%')
                    ->orWhere('notes', 'like', '%'.$order->publicOrderNumber().'%');
            })
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('notes');

        foreach ($visitNotes as $notes) {
            $push($this->titleFromNotes((string) $notes));
        }

        $push($this->titleFromNotifications($order));

        $resolved = null;
        foreach ($candidates as $name) {
            if (strtolower($name) !== 'product') {
                $resolved = $name;
                break;
            }
        }
        if ($resolved === null && $candidates !== []) {
            $resolved = $candidates[0];
        }
        $resolved = $resolved ?? 'Product';

        // Persist recovered title onto mapping so future calendar reads stay cheap.
        if ($mapping
            && strcasecmp($resolved, 'Product') !== 0
            && blank($mapping->product_title)
            && \Illuminate\Support\Facades\Schema::hasColumn($mapping->getTable(), 'product_title')
        ) {
            $mapping->forceFill(['product_title' => $resolved])->saveQuietly();
        }

        return $resolved;
    }

    private function titleFromNotifications(Order $order): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            return null;
        }

        $orderId = (int) $order->id;
        $orderNumber = $order->publicOrderNumber();

        $rows = \Illuminate\Support\Facades\DB::table('notifications')
            ->where(function ($q) use ($orderId, $orderNumber) {
                $q->where('data', 'like', '%"order_id":'.$orderId.'%')
                    ->orWhere('data', 'like', '%"order_id": '.$orderId.'%')
                    ->orWhere('data', 'like', '%"order_number":"'.$orderNumber.'"%');
            })
            ->orderByDesc('id')
            ->limit(30)
            ->pluck('data');

        foreach ($rows as $raw) {
            $payload = is_string($raw) ? json_decode($raw, true) : null;
            if (! is_array($payload)) {
                continue;
            }

            foreach (['products', 'product_ordered'] as $key) {
                foreach ($payload[$key] ?? [] as $product) {
                    if (! is_array($product)) {
                        continue;
                    }
                    $name = trim((string) ($product['name'] ?? ''));
                    if ($name !== '' && strcasecmp($name, 'Product') !== 0) {
                        return $name;
                    }
                }
            }

            $message = (string) ($payload['message'] ?? '');
            if ($message !== '' && preg_match('/paid for\s+(.+?)\.\s*Status:/i', $message, $m)) {
                $name = trim($m[1]);
                if ($name !== '' && strcasecmp($name, 'Product') !== 0) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function titleFromNotes(string $notes): ?string
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        if ($clean === '') {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));
        $first = $parts[0] ?? null;
        if (! is_string($first) || $first === '') {
            return null;
        }
        if (preg_match('/^Recreated from Order/i', $first)
            || preg_match('/^Job #\d+$/i', $first)
            || preg_match('/^order_\d+$/i', $first)) {
            return null;
        }

        return $first;
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
