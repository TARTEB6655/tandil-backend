<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class AdminOrderStatisticsPayload
{
    /**
     * @param  EloquentCollection<int, Order>  $orders
     * @return Collection<int, array<string, mixed>>
     */
    public static function formatMany(EloquentCollection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        $orders->loadMissing([
            'user',
            'items.product.services',
            'vendorMappings.vendor.profile',
            'vendorMappings.vendor.user',
        ]);

        $visitsByOrderId = self::visitsGroupedByOrderId($orders);

        return $orders->map(function (Order $order) use ($visitsByOrderId) {
            return self::format($order, $visitsByOrderId[$order->id] ?? collect());
        })->values();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return array<string, mixed>
     */
    public static function format(Order $order, Collection $visits): array
    {
        $vendorRows = $order->vendorMappings->map(function ($mapping) {
            $vendor = $mapping->vendor;
            $name = $vendor?->profile?->business_name
                ?? $vendor?->profile?->store_name
                ?? $vendor?->user?->name
                ?? null;

            return [
                'vendor_order_mapping_id' => $mapping->id,
                'vendor_id' => $mapping->vendor_id,
                'vendor_name' => $name,
                'vendor_order_status' => $mapping->status,
                'product_title' => $mapping->product_title,
            ];
        })->values();

        $supervisors = $visits
            ->pluck('supervisor')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($user) => self::personPayload($user, 'supervisor'))
            ->values();

        $serviceVisits = $visits->map(function (Visit $visit) {
            return [
                'visit_id' => $visit->id,
                'status' => $visit->status,
                'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
                'scheduled_time' => $visit->scheduled_time,
                'order_item_id' => $visit->order_item_id,
                'supervisor' => $visit->supervisor
                    ? self::personPayload($visit->supervisor, 'supervisor')
                    : null,
                'technician' => $visit->technician
                    ? self::personPayload($visit->technician, 'technician')
                    : null,
            ];
        })->values();

        $fulfillmentTypes = $order->items
            ->map(fn ($item) => OrderFulfillmentType::forOrderItem($item))
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $order->id,
            'order_number' => $order->publicOrderNumber(),
            'order_number_short' => $order->publicOrderNumberDigits(),
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'total_amount' => (float) $order->total_amount,
            'refund_amount' => (float) ($order->refund_amount ?? 0),
            'fulfillment_types' => $fulfillmentTypes,
            'is_marketplace' => $vendorRows->isNotEmpty(),
            'is_service' => in_array(OrderFulfillmentType::SERVICE, $fulfillmentTypes, true) || $serviceVisits->isNotEmpty(),
            'vendors' => $vendorRows,
            'supervisors' => $supervisors,
            'contractors' => $supervisors,
            'service_visits' => $serviceVisits,
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name ?? $order->guest_full_name ?? 'Guest',
                'email' => $order->user?->email ?? $order->guest_email,
            ],
            'created_at' => $order->created_at?->format('c'),
            'updated_at' => $order->updated_at?->format('c'),
        ];
    }

    /**
     * @param  EloquentCollection<int, Order>  $orders
     * @return array<int, Collection<int, Visit>>
     */
    private static function visitsGroupedByOrderId(EloquentCollection $orders): array
    {
        $orderIds = $orders->pluck('id')->all();
        $itemToOrder = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $itemToOrder[(int) $item->id] = (int) $order->id;
            }
        }

        $itemIds = array_keys($itemToOrder);
        if ($orderIds === [] && $itemIds === []) {
            return [];
        }

        $visits = Visit::query()
            ->where(function ($query) use ($orderIds, $itemIds) {
                $first = true;
                if ($orderIds !== []) {
                    $query->whereIn('order_id', $orderIds);
                    $first = false;
                }
                if ($itemIds !== []) {
                    if ($first) {
                        $query->whereIn('order_item_id', $itemIds);
                    } else {
                        $query->orWhereIn('order_item_id', $itemIds);
                    }
                }
            })
            ->with(['supervisor:id,name,email,phone,role', 'technician:id,name,email,phone,role'])
            ->get();

        $grouped = [];
        foreach ($visits as $visit) {
            $orderId = (int) ($visit->order_id ?? 0);
            if ($orderId <= 0 && $visit->order_item_id) {
                $orderId = (int) ($itemToOrder[(int) $visit->order_item_id] ?? 0);
            }
            if ($orderId <= 0) {
                continue;
            }
            if (! isset($grouped[$orderId])) {
                $grouped[$orderId] = collect();
            }
            $grouped[$orderId]->push($visit);
        }

        return $grouped;
    }

    /**
     * @return array{id: int, name: string|null, email: string|null, phone: string|null, role: string}
     */
    private static function personPayload(\App\Models\User $user, string $roleLabel): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role ?? $roleLabel,
        ];
    }
}
