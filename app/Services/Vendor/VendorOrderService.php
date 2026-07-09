<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorOrderService
{
    public function listForVendor(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $q = VendorOrderMapping::with([
            'order.user',
            'order.shippingAddress',
            'order.items.product.primaryImage',
        ])->where('vendor_id', $vendor->id);

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $q->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%");

                if (preg_match('/VND-(\d{4})-(\d+)/i', $search, $matches)) {
                    $query->orWhere('id', (int) $matches[2]);
                }

                $query->orWhereHas('order', function ($oq) use ($search) {
                    $oq->where('id', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhere('guest_full_name', 'like', "%{$search}%");
                });
            });
        }

        return $q->latest()->paginate($perPage);
    }

    /**
     * @return array<string, int>
     */
    public function statusSummary(Vendor $vendor): array
    {
        $rows = VendorOrderMapping::query()
            ->where('vendor_id', $vendor->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $rows->sum();
        $delivered = (int) ($rows[VendorOrderStatus::Delivered->value] ?? 0);

        return [
            'total' => $total,
            'pending' => (int) ($rows[VendorOrderStatus::Pending->value] ?? 0),
            'confirmed' => (int) ($rows[VendorOrderStatus::Confirmed->value] ?? 0),
            'processing' => (int) ($rows[VendorOrderStatus::Processing->value] ?? 0),
            'shipped' => (int) ($rows[VendorOrderStatus::Shipped->value] ?? 0),
            'delivered' => $delivered,
            'cancelled' => (int) ($rows[VendorOrderStatus::Cancelled->value] ?? 0),
        ];
    }

    public function updateStatus(
        VendorOrderMapping $mapping,
        VendorOrderStatus $status,
        User $user,
        ?string $note = null,
        ?string $trackingNumber = null
    ): VendorOrderMapping {
        return DB::transaction(function () use ($mapping, $status, $user, $note, $trackingNumber) {
            $updates = ['status' => $status->value];

            if ($trackingNumber !== null && $trackingNumber !== '') {
                $updates['tracking_number'] = $trackingNumber;
            } elseif ($status === VendorOrderStatus::Shipped && empty($mapping->tracking_number)) {
                $updates['tracking_number'] = $this->defaultTrackingNumber($mapping);
            }

            $mapping->update($updates);

            VendorOrderStatusLog::create([
                'vendor_order_mapping_id' => $mapping->id,
                'status' => $status->value,
                'changed_by' => $user->id,
                'note' => $note,
            ]);

            return $mapping->fresh([
                'order.user',
                'order.shippingAddress',
                'order.items.product.primaryImage',
                'statusLogs',
            ]);
        });
    }

    public function formatListItem(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;
        $status = $mapping->statusEnum();
        $currency = $this->currency();
        $vendorItems = $this->vendorOrderItems($mapping);
        $primaryProduct = $this->formatPrimaryProduct($vendorItems, $currency);

        return [
            'id' => $mapping->id,
            'order_id' => $mapping->order_id,
            'order_number' => $this->orderNumber($mapping),
            'order_date' => $this->formatDateTime($order?->created_at),
            'order_date_label' => $this->formatDate($order?->created_at),
            'status' => $status->value,
            'status_label' => $status->label(),
            'customer' => $this->formatCustomer($order),
            'product' => $primaryProduct,
            'product_count' => $vendorItems->count(),
            'currency' => $currency,
            'total_amount' => (float) $mapping->total_amount,
            'actions' => $this->resolveActions($status),
        ];
    }

    public function formatDetail(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;
        $status = $mapping->statusEnum();
        $currency = $this->currency();
        $vendorItems = $this->vendorOrderItems($mapping);

        return [
            'id' => $mapping->id,
            'order_id' => $mapping->order_id,
            'order_number' => $this->orderNumber($mapping),
            'status' => $status->value,
            'status_label' => $status->label(),
            'order_date' => $this->formatDateTime($order?->created_at),
            'order_date_label' => $this->formatDate($order?->created_at),
            'delivery_date' => $this->formatDate($order?->estimated_arrival),
            'delivery_date_label' => $order?->estimated_arrival
                ? $this->formatDate($order->estimated_arrival)
                : null,
            'payment_method' => $this->paymentMethodLabel($order),
            'payment_status' => $this->paymentStatusLabel($order?->payment_status),
            'tracking_number' => $mapping->tracking_number,
            'currency' => $currency,
            'subtotal' => (float) $mapping->subtotal,
            'tax_amount' => (float) $mapping->tax_amount,
            'shipping_amount' => (float) $mapping->shipping_amount,
            'total_amount' => (float) $mapping->total_amount,
            'customer' => $this->formatCustomer($order, includeAddress: true),
            'order_notes' => $order?->special_instructions,
            'products' => $vendorItems->map(fn (OrderItem $item) => $this->formatProductLine($item, $currency))->values()->all(),
            'product' => $this->formatPrimaryProduct($vendorItems, $currency),
            'status_timeline' => $this->buildStatusTimeline($mapping),
            'available_statuses' => $this->availableStatuses($status),
            'actions' => $this->resolveActions($status),
            'order_info' => [
                'placed_at' => $this->formatDateTime($order?->created_at),
                'estimated_delivery' => $this->formatDate($order?->estimated_arrival),
                'payment_reference' => $order?->payment_reference,
                'transaction_id' => $order?->transaction_id,
            ],
        ];
    }

    public function orderNumber(VendorOrderMapping $mapping): string
    {
        $year = $mapping->created_at?->year ?? now()->year;

        return sprintf('VND-%d-%s', $year, $this->paddedId($mapping->id));
    }

    public function defaultTrackingNumber(VendorOrderMapping $mapping): string
    {
        $year = now()->year;

        return sprintf('TRK-%d-%s', $year, $this->paddedId($mapping->id));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function availableStatuses(VendorOrderStatus $current): array
    {
        return array_map(
            fn (VendorOrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            $this->allowedNextStatuses($current)
        );
    }

    /**
     * @return list<VendorOrderStatus>
     */
    public function allowedNextStatuses(VendorOrderStatus $current): array
    {
        return match ($current) {
            VendorOrderStatus::Pending => [VendorOrderStatus::Confirmed, VendorOrderStatus::Cancelled],
            VendorOrderStatus::Confirmed => [VendorOrderStatus::Processing, VendorOrderStatus::Shipped, VendorOrderStatus::Cancelled],
            VendorOrderStatus::Processing => [VendorOrderStatus::Shipped, VendorOrderStatus::Cancelled],
            VendorOrderStatus::Shipped => [VendorOrderStatus::Delivered],
            VendorOrderStatus::Delivered, VendorOrderStatus::Cancelled => [],
        };
    }

    /**
     * @return list<array{step: string, label: string, status: string, date: ?string}>
     */
    public function buildStatusTimeline(VendorOrderMapping $mapping): array
    {
        $current = $mapping->statusEnum();
        $logDates = $mapping->relationLoaded('statusLogs')
            ? $mapping->statusLogs->keyBy('status')
            : collect();

        if ($current === VendorOrderStatus::Cancelled) {
            $cancelledLog = $logDates->get(VendorOrderStatus::Cancelled->value);

            return [
                [
                    'step' => 'placed',
                    'label' => 'Order Placed',
                    'status' => 'completed',
                    'date' => $this->formatDateTime($mapping->order?->created_at),
                ],
                [
                    'step' => 'cancelled',
                    'label' => 'Cancelled',
                    'status' => 'cancelled',
                    'date' => $this->formatDateTime($cancelledLog?->created_at ?? $mapping->cancelled_at),
                ],
            ];
        }

        $steps = [
            ['step' => 'placed', 'label' => 'Order Placed', 'status' => VendorOrderStatus::Pending],
            ['step' => 'confirmed', 'label' => 'Confirmed', 'status' => VendorOrderStatus::Confirmed],
            ['step' => 'processing', 'label' => 'Processing', 'status' => VendorOrderStatus::Processing],
            ['step' => 'shipped', 'label' => 'Shipped', 'status' => VendorOrderStatus::Shipped],
            ['step' => 'delivered', 'label' => 'Delivered', 'status' => VendorOrderStatus::Delivered],
        ];

        $order = array_search($current->value, array_map(fn ($s) => $s['status']->value, $steps), true);
        if ($order === false) {
            $order = 0;
        }

        $timeline = [];
        foreach ($steps as $index => $step) {
            $log = $logDates->get($step['status']->value);
            $date = $index === 0
                ? ($mapping->order?->created_at ?? $log?->created_at)
                : $log?->created_at;

            if ($index < $order) {
                $stepStatus = 'completed';
            } elseif ($index === $order) {
                $stepStatus = 'current';
            } else {
                $stepStatus = 'pending';
            }

            $timeline[] = [
                'step' => $step['step'],
                'label' => $step['label'],
                'status' => $stepStatus,
                'date' => $this->formatDateTime($date),
            ];
        }

        return $timeline;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveActions(VendorOrderStatus $status): array
    {
        $canConfirm = $status === VendorOrderStatus::Pending;
        $canShip = in_array($status, [VendorOrderStatus::Confirmed, VendorOrderStatus::Processing], true);
        $canMarkDelivered = $status === VendorOrderStatus::Shipped;
        $canCancel = in_array($status, [
            VendorOrderStatus::Pending,
            VendorOrderStatus::Confirmed,
            VendorOrderStatus::Processing,
        ], true);

        $primaryAction = null;
        $primaryActionLabel = null;
        if ($canConfirm) {
            $primaryAction = 'confirm';
            $primaryActionLabel = 'Confirm';
        } elseif ($canShip) {
            $primaryAction = 'ship';
            $primaryActionLabel = 'Ship';
        } elseif ($canMarkDelivered) {
            $primaryAction = 'mark_delivered';
            $primaryActionLabel = 'Mark Delivered';
        }

        return [
            'can_view' => true,
            'can_confirm' => $canConfirm,
            'can_ship' => $canShip,
            'can_mark_delivered' => $canMarkDelivered,
            'can_cancel' => $canCancel,
            'primary_action' => $primaryAction,
            'primary_action_label' => $primaryActionLabel,
        ];
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function vendorOrderItems(VendorOrderMapping $mapping): Collection
    {
        $order = $mapping->order;
        if ($order === null || ! $order->relationLoaded('items')) {
            return collect();
        }

        return $order->items->filter(
            fn (OrderItem $item) => (int) ($item->product?->vendor_id ?? 0) === (int) $mapping->vendor_id
        )->values();
    }

    /**
     * @param  Collection<int, OrderItem>  $vendorItems
     * @return array<string, mixed>|null
     */
    private function formatPrimaryProduct(Collection $vendorItems, string $currency): ?array
    {
        $first = $vendorItems->first();
        if ($first === null) {
            return null;
        }

        if ($vendorItems->count() === 1) {
            return $this->formatProductLine($first, $currency);
        }

        $name = $vendorItems->first()->product?->name ?? 'Product';
        $qty = (int) $vendorItems->sum('quantity');
        $price = (float) $vendorItems->sum('subtotal');

        return [
            'id' => $first->product_id,
            'name' => $name.' +'.($vendorItems->count() - 1).' more',
            'qty' => $qty,
            'price' => $price,
            'currency' => $currency,
            'image_url' => $first->product?->image_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProductLine(OrderItem $item, string $currency): array
    {
        return [
            'id' => $item->product_id,
            'name' => $item->product?->name ?? 'Product',
            'qty' => (int) $item->quantity,
            'price' => (float) $item->subtotal,
            'unit_price' => (float) $item->price,
            'currency' => $currency,
            'image_url' => $item->product?->image_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCustomer(?Order $order, bool $includeAddress = false): array
    {
        if ($order === null) {
            return [
                'name' => 'Customer',
                'phone' => null,
                'email' => null,
                'location' => null,
            ];
        }

        $location = $this->customerLocationLine($order);
        $customer = [
            'name' => $order->payerDisplayName(),
            'phone' => $order->payerPhone(),
            'email' => $order->payerEmail(),
            'location' => $location,
        ];

        if ($includeAddress) {
            $customer['address'] = $order->getShippingAddressForApi();
            $customer['address_text'] = $order->payerAddressForDisplay();
        }

        return $customer;
    }

    private function customerLocationLine(?Order $order): ?string
    {
        if ($order === null) {
            return null;
        }

        if ($order->isGuestOrder()) {
            $parts = array_filter([$order->guest_city, $order->guest_country]);

            return $parts !== [] ? implode(', ', $parts) : null;
        }

        $address = $order->shippingAddress;
        if ($address === null) {
            return null;
        }

        $parts = array_filter([$address->city, $address->country]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private function paymentMethodLabel(?Order $order): string
    {
        if ($order === null) {
            return '—';
        }

        $method = strtolower((string) ($order->payment_method ?? ''));

        return match ($method) {
            'stripe', 'card', 'credit_card' => 'Credit Card',
            'paypal' => 'PayPal',
            'cod', 'cash_on_delivery' => 'Cash on Delivery',
            default => $order->paymentMethodLabel(),
        };
    }

    private function paymentStatusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            default => $status !== null && $status !== '' ? ucfirst($status) : '—',
        };
    }

    private function currency(): string
    {
        return strtoupper((string) config('shop.currency', 'AED'));
    }

    private function paddedId(int $id): string
    {
        return str_pad((string) $id, max(4, strlen((string) $id)), '0', STR_PAD_LEFT);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('d/m/Y');
    }
}
