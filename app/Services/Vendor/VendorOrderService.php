<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorOrderService
{
    public function listForVendor(Vendor $vendor, array $filters = [], int $perPage = 15, bool $withProductSummaries = true): LengthAwarePaginator
    {
        $vendorId = $vendor->id;
        $perPage = max(1, min($perPage, 50));

        $q = VendorOrderMapping::query()
            ->select([
                'id',
                'order_id',
                'vendor_id',
                'status',
                'tracking_number',
                'subtotal',
                'tax_amount',
                'shipping_amount',
                'total_amount',
                'commission_amount',
                'cancelled_at',
                'created_at',
                'updated_at',
            ])
            ->where('vendor_id', $vendorId);

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $q->where(function ($query) use ($search) {
                if (ctype_digit($search)) {
                    $query->where('vendor_order_mappings.id', (int) $search)
                        ->orWhere('vendor_order_mappings.order_id', (int) $search);

                    return;
                }

                if (preg_match('/VND-(\d{4})-(\d+)/i', $search, $matches)) {
                    $query->where('vendor_order_mappings.id', (int) $matches[2]);

                    return;
                }

                $like = '%'.$search.'%';
                $query->where('vendor_order_mappings.tracking_number', 'like', $like)
                    ->orWhereIn('vendor_order_mappings.order_id', function ($sub) use ($like) {
                        $sub->select('id')
                            ->from('orders')
                            ->where('guest_email', 'like', $like)
                            ->orWhere('guest_full_name', 'like', $like);
                    });
            });
        }

        $paginator = $q->with($this->listRelations())
            ->latest('vendor_order_mappings.created_at')
            ->paginate($perPage);

        if ($withProductSummaries) {
            $this->attachListProductSummaries(collect($paginator->items()), $vendorId);
        }

        return $paginator;
    }

    /**
     * @param  Collection<int, VendorOrderMapping>  $mappings
     */
    public function attachListProductSummaries(Collection $mappings, int $vendorId): void
    {
        $orderIds = $mappings->pluck('order_id')->unique()->filter()->values();
        if ($orderIds->isEmpty()) {
            return;
        }

        $itemsByOrder = OrderItem::query()
            ->select(['order_items.id', 'order_items.order_id', 'order_items.product_id', 'order_items.quantity', 'order_items.price', 'order_items.subtotal'])
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.vendor_id', $vendorId)
            ->whereIn('order_items.order_id', $orderIds)
            ->with(['product' => fn ($query) => $query->select(['id', 'vendor_id', 'name', 'image'])])
            ->orderBy('order_items.id')
            ->get()
            ->groupBy('order_id');

        foreach ($mappings as $mapping) {
            $mapping->setRelation(
                'vendorListItems',
                $itemsByOrder->get($mapping->order_id, collect())
            );
        }
    }

    /**
     * Minimal relations for the mobile orders list (detail view loads more).
     *
     * @return array<string, mixed>
     */
    private function listRelations(): array
    {
        return [
            'order' => fn ($query) => $query->select([
                'id',
                'user_id',
                'guest_email',
                'guest_full_name',
                'guest_phone',
                'guest_city',
                'guest_country',
                'shipping_address_id',
                'special_instructions',
                'created_at',
            ]),
            'order.user' => fn ($query) => $query->select(['id', 'name', 'email', 'phone']),
            'order.shippingAddress' => fn ($query) => $query->select([
                'id',
                'full_name',
                'phone_number',
                'city',
                'country',
            ]),
        ];
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

            // Reuse the same constrained eager-load path as GET detail (no full-table fresh).
            return $this->findMappingForVendorById((int) $mapping->vendor_id, (int) $mapping->id, 'detail')
                ?? $mapping->fresh(['order.user', 'order.shippingAddress', 'statusLogs']);
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
            'is_demo' => $this->isDemoOrder($order),
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
            'actions' => array_merge($this->resolveActions($status), $this->resolveDocumentActions($mapping)),
            'order_info' => [
                'placed_at' => $this->formatDateTime($order?->created_at),
                'estimated_delivery' => $this->formatDate($order?->estimated_arrival),
                'payment_reference' => $order?->payment_reference,
                'transaction_id' => $order?->transaction_id,
            ],
        ];
    }

    /**
     * Dedicated track payload for vendor order tracking UI.
     *
     * @return array<string, mixed>
     */
    public function formatTrack(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;
        $status = $mapping->statusEnum();
        $currency = $this->currency();
        $vendorItems = $this->vendorOrderItems($mapping);
        $latestLog = $mapping->relationLoaded('statusLogs')
            ? $mapping->statusLogs->last()
            : null;

        return [
            'id' => $mapping->id,
            'order_id' => $mapping->order_id,
            'order_number' => $this->orderNumber($mapping),
            'tracking_number' => $mapping->tracking_number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'current_status' => $status->label(),
            'order' => [
                'id' => $mapping->id,
                'shop_order_id' => $mapping->order_id,
                'order_number' => $this->orderNumber($mapping),
                'status' => $status->value,
                'status_label' => $status->label(),
                'tracking_number' => $mapping->tracking_number,
                'order_date' => $this->formatDateTime($order?->created_at),
                'delivery_date' => $this->formatDate($order?->estimated_arrival),
                'payment_method' => $this->paymentMethodLabel($order),
                'payment_status' => $this->paymentStatusLabel($order?->payment_status),
                'currency' => $currency,
                'subtotal' => (float) $mapping->subtotal,
                'tax_amount' => (float) $mapping->tax_amount,
                'shipping_amount' => (float) $mapping->shipping_amount,
                'total_amount' => (float) $mapping->total_amount,
                'customer' => $this->formatCustomer($order, includeAddress: true),
                'products' => $vendorItems->map(fn (OrderItem $item) => $this->formatProductLine($item, $currency))->values()->all(),
                'order_notes' => $order?->special_instructions,
            ],
            'tracking' => [
                'status' => $status->value,
                'payment_status' => $order?->payment_status,
                'tracking_number' => $mapping->tracking_number,
                'timeline' => $this->buildTrackTimeline($mapping),
                'last_note' => $latestLog?->note,
                'cancellation_reason' => $mapping->cancellation_reason,
                'created_at' => $order?->created_at?->format('c'),
                'updated_at' => $mapping->updated_at?->format('c'),
                'cancelled_at' => $mapping->cancelled_at?->format('c'),
            ],
            'customer' => $this->formatCustomer($order, includeAddress: true),
            'products' => $vendorItems->map(fn (OrderItem $item) => $this->formatProductLine($item, $currency))->values()->all(),
            'actions' => array_merge($this->resolveActions($status), $this->resolveDocumentActions($mapping)),
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
     * Timeline for vendor order track API (Pending → Delivered, or Placed → Cancelled).
     *
     * @return list<array{key: string, label: string, description: string, completed: bool, current: bool, timestamp: ?string, date: ?string}>
     */
    public function buildTrackTimeline(VendorOrderMapping $mapping): array
    {
        $current = $mapping->statusEnum();
        $logDates = $mapping->relationLoaded('statusLogs')
            ? $mapping->statusLogs->keyBy('status')
            : collect();

        $descriptions = [
            VendorOrderStatus::Pending->value => 'Order placed successfully',
            VendorOrderStatus::Confirmed->value => 'Vendor confirmed the order',
            VendorOrderStatus::Processing->value => 'Order is being prepared',
            VendorOrderStatus::Shipped->value => 'Order has been shipped',
            VendorOrderStatus::Delivered->value => 'Order delivered to customer',
            VendorOrderStatus::Cancelled->value => 'Order was cancelled',
        ];

        if ($current === VendorOrderStatus::Cancelled) {
            $cancelledLog = $logDates->get(VendorOrderStatus::Cancelled->value);
            $cancelledAt = $cancelledLog?->created_at ?? $mapping->cancelled_at;

            return [
                [
                    'key' => 'pending',
                    'label' => 'Order Placed',
                    'description' => $descriptions[VendorOrderStatus::Pending->value],
                    'completed' => true,
                    'current' => false,
                    'timestamp' => $this->formatTime($mapping->order?->created_at),
                    'date' => $this->formatDateTime($mapping->order?->created_at),
                ],
                [
                    'key' => 'cancelled',
                    'label' => 'Cancelled',
                    'description' => filled($mapping->cancellation_reason)
                        ? (string) $mapping->cancellation_reason
                        : $descriptions[VendorOrderStatus::Cancelled->value],
                    'completed' => true,
                    'current' => true,
                    'timestamp' => $this->formatTime($cancelledAt),
                    'date' => $this->formatDateTime($cancelledAt),
                ],
            ];
        }

        $steps = [
            ['key' => 'pending', 'label' => 'Order Placed', 'status' => VendorOrderStatus::Pending],
            ['key' => 'confirmed', 'label' => 'Confirmed', 'status' => VendorOrderStatus::Confirmed],
            ['key' => 'processing', 'label' => 'Processing', 'status' => VendorOrderStatus::Processing],
            ['key' => 'shipped', 'label' => 'Shipped', 'status' => VendorOrderStatus::Shipped],
            ['key' => 'delivered', 'label' => 'Delivered', 'status' => VendorOrderStatus::Delivered],
        ];

        $currentIndex = array_search($current->value, array_map(fn ($s) => $s['status']->value, $steps), true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $timeline = [];
        foreach ($steps as $index => $step) {
            $log = $logDates->get($step['status']->value);
            $isCurrent = $index === $currentIndex;
            $completed = $index <= $currentIndex;

            if ($index === 0) {
                $date = $mapping->order?->created_at ?? $log?->created_at;
            } elseif ($log?->created_at) {
                $date = $log->created_at;
            } elseif ($isCurrent) {
                $date = $mapping->updated_at;
            } else {
                $date = null;
            }

            $timeline[] = [
                'key' => $step['key'],
                'label' => $step['label'],
                'description' => $descriptions[$step['status']->value],
                'completed' => $completed,
                'current' => $isCurrent,
                'timestamp' => $completed ? $this->formatTime($date) : null,
                'date' => $completed ? $this->formatDateTime($date) : null,
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

    public function findMappingForVendor(Vendor $vendor, int $id, string $mode = 'detail'): ?VendorOrderMapping
    {
        return $this->findMappingForVendorById((int) $vendor->id, $id, $mode);
    }

    /**
     * @param  'detail'|'contact'|'pdf'  $mode
     */
    public function findMappingForVendorById(int $vendorId, int $id, string $mode = 'detail'): ?VendorOrderMapping
    {
        $with = match ($mode) {
            'contact' => [
                'order' => fn ($query) => $query->select($this->orderColumnsForApi()),
                'order.user' => fn ($query) => $query->select(['id', 'name', 'email', 'phone']),
                'order.shippingAddress' => fn ($query) => $query->select([
                    'id',
                    'full_name',
                    'phone_number',
                    'street_address',
                    'city',
                    'state',
                    'zip_code',
                    'country',
                ]),
            ],
            'pdf' => array_merge($this->detailRelations($vendorId), [
                'vendor.profile' => fn ($query) => $query->select(['id', 'vendor_id', 'business_name', 'email', 'phone']),
            ]),
            default => $this->detailRelations($vendorId),
        };

        $mapping = VendorOrderMapping::with($with)
            ->where('vendor_id', $vendorId)
            ->where('id', $id)
            ->first();

        if ($mapping !== null && $mapping->relationLoaded('order') && $mapping->order !== null
            && $mapping->order->relationLoaded('items')) {
            $mapping->setRelation(
                'vendorListItems',
                $mapping->order->items
            );
        }

        return $mapping;
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRelations(int $vendorId): array
    {
        return [
            'order' => fn ($query) => $query->select($this->orderColumnsForApi()),
            'order.user' => fn ($query) => $query->select(['id', 'name', 'email', 'phone']),
            'order.shippingAddress' => fn ($query) => $query->select([
                'id',
                'full_name',
                'phone_number',
                'street_address',
                'city',
                'state',
                'zip_code',
                'country',
            ]),
            'order.items' => fn ($query) => $query
                ->select(['id', 'order_id', 'product_id', 'quantity', 'price', 'subtotal'])
                ->whereIn('product_id', function ($sub) use ($vendorId) {
                    $sub->select('id')->from('products')->where('vendor_id', $vendorId);
                }),
            'order.items.product' => fn ($query) => $query->select(['id', 'vendor_id', 'name', 'image']),
            'order.items.product.primaryImage',
            'statusLogs' => fn ($query) => $query
                ->select(['id', 'vendor_order_mapping_id', 'status', 'note', 'created_at'])
                ->orderBy('created_at'),
        ];
    }

    /**
     * @return list<string>
     */
    private function orderColumnsForApi(): array
    {
        return [
            'id',
            'user_id',
            'guest_email',
            'guest_full_name',
            'guest_phone',
            'guest_street_address',
            'guest_city',
            'guest_state',
            'guest_zip_code',
            'guest_country',
            'shipping_address_id',
            'payment_method',
            'payment_status',
            'payment_reference',
            'transaction_id',
            'special_instructions',
            'estimated_arrival',
            'created_at',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatContact(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;
        $contactActions = $this->buildContactActions($order);
        $customer = $this->formatCustomer($order, includeAddress: true);

        return [
            'order_id' => $mapping->id,
            'order_number' => $this->orderNumber($mapping),
            'customer' => $customer,
            'contact_actions' => $contactActions,
            'can_contact' => $contactActions !== [],
            'preferred_action' => $contactActions[0] ?? null,
        ];
    }

    public function buildOrderPdfBinary(VendorOrderMapping $mapping, string $type = 'invoice'): string
    {
        if (! $mapping->relationLoaded('vendor') || $mapping->vendor === null
            || ! $mapping->relationLoaded('order')) {
            $reloaded = $this->findMappingForVendorById((int) $mapping->vendor_id, (int) $mapping->id, 'pdf');
            if ($reloaded !== null) {
                $mapping = $reloaded;
            } else {
                $mapping->loadMissing([
                    'order.user',
                    'order.shippingAddress',
                    'order.items.product',
                    'vendor.profile',
                ]);
            }
        }

        $order = $this->formatDetail($mapping);
        $businessName = $mapping->vendor?->profile?->business_name ?? 'Vendor';

        return Pdf::loadView('shared.vendor-order-invoice-pdf', [
            'documentType' => $type,
            'documentTitle' => $type === 'invoice' ? 'Tax Invoice' : 'Order Summary',
            'businessName' => $businessName,
            'generatedAt' => now()->format('d M Y, H:i'),
            'order' => $order,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    public function invoiceFilename(VendorOrderMapping $mapping): string
    {
        return strtolower($this->orderNumber($mapping)).'_invoice.pdf';
    }

    public function downloadFilename(VendorOrderMapping $mapping): string
    {
        return strtolower($this->orderNumber($mapping)).'_order.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveDocumentActions(VendorOrderMapping $mapping): array
    {
        $contactActions = $this->buildContactActions($mapping->order);
        $id = $mapping->id;

        return [
            'can_contact_customer' => $contactActions !== [],
            'can_print_invoice' => true,
            'can_download_order' => true,
            'contact_endpoint' => "/api/vendor/orders/{$id}/contact",
            'invoice_endpoint' => "/api/vendor/orders/{$id}/invoice",
            'download_endpoint' => "/api/vendor/orders/{$id}/download",
            'track_endpoint' => "/api/vendor/orders/{$id}/track",
        ];
    }

    /**
     * @return list<array{type: string, label: string, url: string}>
     */
    private function buildContactActions(?Order $order): array
    {
        if ($order === null) {
            return [];
        }

        $actions = [];
        $phone = $order->payerPhone();
        $email = $order->payerEmail();

        if ($phone !== null && trim($phone) !== '') {
            $actions[] = [
                'type' => 'call',
                'label' => 'Call Customer',
                'url' => 'tel:'.preg_replace('/\s+/', '', $phone),
            ];

            $digits = $this->normalizePhoneDigits($phone);
            if ($digits !== null) {
                $actions[] = [
                    'type' => 'whatsapp',
                    'label' => 'WhatsApp',
                    'url' => 'https://wa.me/'.$digits,
                ];
            }
        }

        if ($email !== null && trim($email) !== '') {
            $actions[] = [
                'type' => 'email',
                'label' => 'Email Customer',
                'url' => 'mailto:'.$email,
            ];
        }

        return $actions;
    }

    private function normalizePhoneDigits(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function vendorOrderItems(VendorOrderMapping $mapping): Collection
    {
        if ($mapping->relationLoaded('vendorListItems')) {
            return $mapping->getRelation('vendorListItems');
        }

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

    private function isDemoOrder(?Order $order): bool
    {
        if ($order === null) {
            return false;
        }

        $marker = \Database\Seeders\VendorDemoOrdersSeeder::DEMO_MARKER;
        $instructions = (string) ($order->special_instructions ?? '');

        return str_starts_with($instructions, $marker);
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

    private function formatTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('g:i A');
    }
}
