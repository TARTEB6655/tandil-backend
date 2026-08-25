<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Support\OrderFulfillmentType;
use App\Support\OrderTrackingTimeline;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $status = (string) $filters['status'];
            // Vendor portal filters by fulfillment mapping status (product workflow).
            $q->where('status', $status);
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
                'order_status',
                'payment_status',
                'paid_at',
                'special_instructions',
                'created_at',
                'updated_at',
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
        // Vendor portal only lists product fulfillment mappings — count by vendor status.
        $rows = VendorOrderMapping::query()
            ->where('vendor_id', $vendor->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $normalize = static function (?string $raw): string {
            $status = strtolower(trim((string) $raw));

            return match ($status) {
                'paid' => 'pending',
                '' => 'pending',
                default => $status,
            };
        };

        $counts = [
            'pending' => 0,
            'confirmed' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
            // Kept for older mobile clients that still read these keys
            'assigned' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];

        foreach ($rows as $rawStatus => $count) {
            $key = $normalize(is_string($rawStatus) ? $rawStatus : (string) $rawStatus);
            if (! array_key_exists($key, $counts)) {
                $key = 'pending';
            }
            $counts[$key] += (int) $count;
        }

        $total = (int) array_sum([
            $counts['pending'],
            $counts['confirmed'],
            $counts['processing'],
            $counts['shipped'],
            $counts['delivered'],
            $counts['cancelled'],
        ]);

        return array_merge(['total' => $total], $counts);
    }

    public function updateStatus(
        VendorOrderMapping $mapping,
        VendorOrderStatus $status,
        User $user,
        ?string $note = null,
        ?string $trackingNumber = null
    ): VendorOrderMapping {
        if ($status === VendorOrderStatus::Delivered) {
            throw ValidationException::withMessages([
                'status' => ['Use POST /api/vendor/orders/{id}/confirm-delivery with the customer OTP to mark delivered.'],
            ]);
        }

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

            $mapping = $this->findMappingForVendorById((int) $mapping->vendor_id, (int) $mapping->id, 'detail')
                ?? $mapping->fresh(['order.user', 'order.shippingAddress', 'statusLogs']);

            if ($status === VendorOrderStatus::Shipped) {
                $mapping = app(VendorDeliveryOtpService::class)->ensureOtpForShipped($mapping);
            }

            app(VendorDeliveryOtpService::class)->syncShopOrderStatus($mapping);

            return $this->findMappingForVendorById((int) $mapping->vendor_id, (int) $mapping->id, 'detail')
                ?? $mapping;
        });
    }

    public function confirmDeliveryWithOtp(VendorOrderMapping $mapping, string $otp, User $user): VendorOrderMapping
    {
        $mapping = app(VendorDeliveryOtpService::class)->confirmWithOtp($mapping, $otp, $user);

        return $this->findMappingForVendorById((int) $mapping->vendor_id, (int) $mapping->id, 'detail')
            ?? $mapping;
    }

    public function formatListItem(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;
        $fulfillment = $mapping->statusEnum();
        $currency = $this->currency();
        $vendorItems = $this->vendorOrderItems($mapping);
        $primaryProduct = $this->formatPrimaryProduct($vendorItems, $currency);

        $shopStatus = OrderTrackingTimeline::normalize((string) ($order?->order_status ?? 'pending'));
        $shopStatusLabel = OrderTrackingTimeline::statusLabel($shopStatus);

        // Vendor product orders: list card matches Order Details (vendor fulfillment status).
        $status = $fulfillment->value;
        $statusLabel = $fulfillment->label();
        $statusIcon = $fulfillment->icon();
        $statusColor = $fulfillment->color();

        return [
            'id' => $mapping->id,
            'order_id' => $mapping->order_id,
            'order_number' => $order?->publicOrderNumber() ?? $this->orderNumber($mapping),
            'order_number_vendor' => $this->orderNumber($mapping),
            'order_date' => $this->formatDateTime($order?->created_at),
            'order_date_label' => $this->formatDate($order?->created_at),
            'status' => $status,
            'status_label' => $statusLabel,
            'status_icon' => $statusIcon,
            'status_color' => $statusColor,
            'current_status' => $statusLabel,
            'shop_status' => $shopStatus,
            'shop_status_label' => $shopStatusLabel,
            'vendor_status' => $fulfillment->value,
            'vendor_status_label' => $fulfillment->label(),
            'vendor_status_icon' => $fulfillment->icon(),
            'vendor_status_color' => $fulfillment->color(),
            'fulfillment_type' => OrderFulfillmentType::PRODUCT,
            'is_demo' => $this->isDemoOrder($order),
            'customer' => $this->formatCustomer($order),
            'product' => $primaryProduct,
            'product_count' => $vendorItems->count(),
            'currency' => $currency,
            'total_amount' => (float) $mapping->total_amount,
            'actions' => $this->resolveActions($fulfillment),
            'track_endpoint' => '/api/vendor/orders/'.$mapping->order_id.'/track',
        ];
    }

    private function shopStatusIcon(string $status): string
    {
        return match (OrderTrackingTimeline::normalize($status)) {
            'pending' => 'clock',
            'processing' => 'gear',
            'confirmed' => 'check',
            'assigned' => 'user',
            'in_progress' => 'wrench',
            'completed' => 'check',
            'delivered' => 'check-circle',
            'cancelled' => 'x',
            default => 'clock',
        };
    }

    private function shopStatusColor(string $status): string
    {
        return match (OrderTrackingTimeline::normalize($status)) {
            'pending', 'processing', 'in_progress' => 'gold',
            'confirmed', 'assigned' => 'blue',
            'completed', 'delivered' => 'green',
            'cancelled' => 'red',
            default => 'grey',
        };
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
            'status_icon' => $status->icon(),
            'status_color' => $status->color(),
            'order_date' => $this->formatDateTime($order?->created_at),
            'order_date_label' => $this->formatDate($order?->created_at),
            'order_date_display' => $this->formatDisplayDateTime($order?->created_at) ?? '—',
            'delivery_date' => $this->formatDate($order?->estimated_arrival),
            'delivery_date_label' => $order?->estimated_arrival
                ? $this->formatDate($order->estimated_arrival)
                : null,
            'delivery_date_display' => $this->formatDisplayDateTime($order?->estimated_arrival) ?? '—',
            'payment_method' => $this->paymentMethodLabel($order),
            'payment_status' => $this->paymentStatusLabel($order?->payment_status),
            'tracking_number' => $mapping->tracking_number,
            'tracking_display' => $this->displayOrDash($mapping->tracking_number),
            'currency' => $currency,
            'subtotal' => (float) $mapping->subtotal,
            'tax_amount' => (float) $mapping->tax_amount,
            'shipping_amount' => (float) $mapping->shipping_amount,
            'total_amount' => (float) $mapping->total_amount,
            'total_amount_label' => $this->moneyLabel((float) $mapping->total_amount, $currency),
            'customer' => $this->formatCustomer($order, includeAddress: true),
            'order_notes' => $order?->special_instructions,
            'products' => $vendorItems->map(fn (OrderItem $item) => $this->formatProductLine($item, $currency))->values()->all(),
            'product' => $this->formatPrimaryProduct($vendorItems, $currency),
            'status_timeline' => $this->buildStatusTimeline($mapping),
            'status_options' => $this->statusOptions($status),
            'available_statuses' => $this->availableStatuses($status),
            'actions' => array_merge($this->resolveActions($status), $this->resolveDocumentActions($mapping)),
            'order_info' => $this->formatOrderInfo($mapping),
        ];
    }

    /**
     * Track payload — same timeline as client GET /api/orders/{id}/track.
     * Product orders → horizontal Pending→Confirmed→Processing→Shipped→Delivered.
     * Service orders → vertical supervisor/technician timeline.
     *
     * @return array<string, mixed>
     */
    public function formatTrack(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;
        $fulfillment = $mapping->statusEnum();
        $currency = $this->currency();
        $vendorItems = $this->vendorOrderItems($mapping);

        $fulfillmentType = $order
            ? OrderTrackingTimeline::fulfillmentType($order)
            : OrderFulfillmentType::PRODUCT;
        $display = $order
            ? OrderTrackingTimeline::displayStatus($order, $mapping)
            : [
                'status' => $fulfillment->value,
                'status_label' => $fulfillment->label(),
                'status_icon' => $fulfillment->icon(),
            ];
        $layout = $order
            ? OrderTrackingTimeline::trackingLayout($order)
            : 'horizontal';

        return [
            'order_id' => $mapping->order_id,
            'vendor_order_id' => $mapping->id,
            'order_number' => $order?->publicOrderNumber() ?? $this->orderNumber($mapping),
            'order_number_short' => $order?->publicOrderNumberDigits(),
            'tracking_number' => $mapping->tracking_number,
            'tracking_display' => $this->displayOrDash($mapping->tracking_number),
            'fulfillment_type' => $fulfillmentType,
            'tracking_layout' => $layout,
            'status' => $display['status'],
            'status_label' => $display['status_label'],
            'status_icon' => $display['status_icon'],
            'current_status' => $display['status_label'],
            'vendor_status' => $fulfillment->value,
            'vendor_status_label' => $fulfillment->label(),
            'vendor_status_icon' => $fulfillment->icon(),
            'vendor_status_color' => $fulfillment->color(),
            'order' => [
                'id' => $mapping->order_id,
                'vendor_order_id' => $mapping->id,
                'order_number' => $order?->publicOrderNumber() ?? $this->orderNumber($mapping),
                'status' => $display['status'],
                'status_label' => $display['status_label'],
                'vendor_status' => $fulfillment->value,
                'tracking_number' => $mapping->tracking_number,
                'currency' => $currency,
                'subtotal' => (float) $mapping->subtotal,
                'tax_amount' => (float) $mapping->tax_amount,
                'shipping_amount' => (float) $mapping->shipping_amount,
                'total_amount' => (float) $mapping->total_amount,
                'total_amount_label' => $this->moneyLabel((float) $mapping->total_amount, $currency),
                'products' => $vendorItems->map(fn (OrderItem $item) => $this->formatProductLine($item, $currency))->values()->all(),
                'customer' => $this->formatCustomer($order, includeAddress: true),
                'order_notes' => $order?->special_instructions,
            ],
            'order_summary' => [
                'order_date' => $this->formatDisplayDateTime($order?->created_at) ?? '—',
                'delivery_date' => $this->formatDisplayDateTime($order?->estimated_arrival) ?? '—',
                'tracking' => $this->displayOrDash($mapping->tracking_number),
                'payment_method' => $this->paymentMethodLabel($order),
                'payment_status' => $this->paymentStatusLabel($order?->payment_status),
                'total' => (float) $mapping->total_amount,
                'currency' => $currency,
                'total_amount_label' => $this->moneyLabel((float) $mapping->total_amount, $currency),
                'special_instructions' => $order?->special_instructions,
            ],
            'tracking' => [
                'fulfillment_type' => $fulfillmentType,
                'layout' => $layout,
                'status' => $display['status'],
                'status_label' => $display['status_label'],
                'payment_status' => $order?->payment_status,
                'tracking_number' => $mapping->tracking_number,
                'timeline' => $this->buildTrackTimeline($mapping),
                'created_at' => $order?->created_at?->format('c'),
                'updated_at' => $order?->updated_at?->format('c'),
                'paid_at' => $order?->paid_at?->format('c'),
                'cancelled_at' => $mapping->cancelled_at?->format('c'),
                'cancellation_reason' => $mapping->cancellation_reason,
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
     * Update Status grid: all 5 workflow buttons with selected/enabled flags.
     *
     * @return list<array{value: string, label: string, icon: string, color: string, selected: bool, enabled: bool, completed: bool}>
     */
    public function statusOptions(VendorOrderStatus $current): array
    {
        $allowed = array_map(
            fn (VendorOrderStatus $status) => $status->value,
            $this->allowedNextStatuses($current)
        );
        $isCancelled = $current === VendorOrderStatus::Cancelled;
        $currentIndex = array_search($current, VendorOrderStatus::workflow(), true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $options = [];
        foreach (VendorOrderStatus::workflow() as $index => $status) {
            $selected = ! $isCancelled && $status === $current;
            $completed = ! $isCancelled && $index < $currentIndex;

            $options[] = [
                'value' => $status->value,
                'label' => $status->label(),
                'icon' => $status->icon(),
                'color' => $isCancelled
                    ? 'grey'
                    : ($selected ? $status->color() : ($completed ? 'gold' : 'grey')),
                'selected' => $selected,
                'enabled' => ! $isCancelled && ($selected || in_array($status->value, $allowed, true)),
                'completed' => $completed,
            ];
        }

        return $options;
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
                $this->timelineStep(
                    VendorOrderStatus::Pending,
                    'completed',
                    $mapping->order?->created_at,
                    completed: true,
                    current: false
                ),
                $this->timelineStep(
                    VendorOrderStatus::Cancelled,
                    'cancelled',
                    $cancelledLog?->created_at ?? $mapping->cancelled_at,
                    completed: true,
                    current: true
                ),
            ];
        }

        $steps = VendorOrderStatus::workflow();
        $currentIndex = array_search($current, $steps, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $timeline = [];
        foreach ($steps as $index => $status) {
            $log = $logDates->get($status->value);
            if ($index === 0) {
                $date = $mapping->order?->created_at ?? $log?->created_at;
            } elseif ($log?->created_at) {
                $date = $log->created_at;
            } elseif ($index === $currentIndex) {
                $date = $mapping->updated_at;
            } else {
                $date = null;
            }

            if ($index < $currentIndex) {
                $stepStatus = 'completed';
            } elseif ($index === $currentIndex) {
                $stepStatus = 'current';
            } else {
                $stepStatus = 'pending';
            }

            $timeline[] = $this->timelineStep(
                $status,
                $stepStatus,
                $date,
                completed: $index <= $currentIndex,
                current: $index === $currentIndex
            );
        }

        return $timeline;
    }

    /**
     * Same service timeline as client track, enriched with icon/color/current for vendor UI.
     *
     * @return list<array{key: string, label: string, icon: string, color: string, description: string, completed: bool, current: bool, timestamp: ?string, date: ?string, date_display: string}>
     */
    public function buildTrackTimeline(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;
        if ($order === null) {
            return [];
        }

        $base = OrderTrackingTimeline::forOrder($order);
        $colors = [
            'pending' => 'gold',
            'processing' => 'gold',
            'confirmed' => 'blue',
            'assigned' => 'blue',
            'in_progress' => 'gold',
            'completed' => 'green',
            'shipped' => 'gold',
            'delivered' => 'green',
            'cancel_order' => 'red',
            'refund_processing' => 'gold',
            'refund_complete' => 'green',
        ];

        $timeline = [];
        foreach ($base as $step) {
            $key = $step['key'];
            $completed = (bool) $step['completed'];
            $isCurrent = (bool) ($step['current'] ?? false);
            $at = $this->resolveStepDate($order, $key, $completed);

            $timeline[] = $this->trackTimelineStep(
                key: $key,
                label: $step['label'],
                icon: $step['icon'] ?? 'clock',
                color: $isCurrent ? ($colors[$key] ?? 'gold') : ($completed ? 'gold' : 'grey'),
                description: $step['description'],
                date: $at,
                completed: $completed,
                current: $isCurrent,
                timestampOverride: $step['timestamp']
            );
        }

        return $timeline;
    }

    private function resolveStepDate(Order $order, string $key, bool $completed): mixed
    {
        if (! $completed) {
            return null;
        }

        return match ($key) {
            'pending' => $order->created_at,
            'processing' => $order->paid_at ?? $order->updated_at,
            'cancel_order' => $order->updated_at,
            'refund_processing', 'refund_complete' => $order->refunded_at ?? $order->updated_at,
            default => $order->updated_at,
        };
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
            $primaryAction = 'confirm_delivery_otp';
            $primaryActionLabel = 'Confirm delivery (OTP)';
        }

        return [
            'can_view' => true,
            'can_confirm' => $canConfirm,
            'can_ship' => $canShip,
            'can_mark_delivered' => $canMarkDelivered,
            'can_confirm_delivery_otp' => $canMarkDelivered,
            'can_cancel' => $canCancel,
            'primary_action' => $primaryAction,
            'primary_action_label' => $primaryActionLabel,
            'confirm_delivery_endpoint' => $canMarkDelivered
                ? '/api/vendor/orders/{id}/confirm-delivery'
                : null,
        ];
    }

    public function findMappingForVendor(Vendor $vendor, int $id, string $mode = 'detail'): ?VendorOrderMapping
    {
        return $this->findMappingForVendorById((int) $vendor->id, $id, $mode);
    }

    /**
     * Resolve by shop/vendor order id first (`order_id` from GET /orders), then mapping id.
     *
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

        $base = fn () => VendorOrderMapping::with($with)->where('vendor_id', $vendorId);

        $mapping = $base()->where('order_id', $id)->first()
            ?? $base()->where('id', $id)->first();

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
            'order_status',
            'payment_method',
            'payment_status',
            'payment_reference',
            'transaction_id',
            'paid_at',
            'refund_amount',
            'refunded_at',
            'special_instructions',
            'estimated_arrival',
            'created_at',
            'updated_at',
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
        $orderId = $mapping->order_id ?: $mapping->id;

        return [
            'can_contact_customer' => $contactActions !== [],
            'can_print_invoice' => true,
            'can_download_order' => true,
            'contact_endpoint' => "/api/vendor/orders/{$orderId}/contact",
            'invoice_endpoint' => "/api/vendor/orders/{$orderId}/invoice",
            'download_endpoint' => "/api/vendor/orders/{$orderId}/download",
            'track_endpoint' => "/api/vendor/orders/{$orderId}/track",
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
            'qty_label' => 'Qty '.$qty,
            'price' => $price,
            'price_label' => $this->moneyLabel($price, $currency),
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
            'qty_label' => 'Qty '.(int) $item->quantity,
            'price' => (float) $item->subtotal,
            'price_label' => $this->moneyLabel((float) $item->subtotal, $currency),
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
                'phone_display' => 'No phone',
                'email' => null,
                'email_display' => '—',
                'location' => null,
            ];
        }

        $location = $this->customerLocationLine($order);
        $phone = $order->payerPhone();
        $email = $order->payerEmail();
        $name = trim((string) $order->payerDisplayName());
        $customer = [
            'name' => $name !== '' ? $name : 'Customer',
            'phone' => $phone,
            'phone_display' => filled($phone) ? $phone : 'No phone',
            'email' => $email,
            'email_display' => $this->displayOrDash($email),
            'location' => $location,
        ];

        if ($includeAddress) {
            $addressText = $order->payerAddressForDisplay();
            $customer['address'] = $order->getShippingAddressForApi();
            $customer['address_text'] = $addressText;
            $customer['address_display'] = $this->displayOrDash($addressText !== '' ? $addressText : null);
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

    private function formatDisplayDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = Carbon::parse($value);

        return $date->format('j M Y').' at '.$date->format('g:i A');
    }

    private function displayOrDash(?string $value): string
    {
        return filled($value) ? $value : '—';
    }

    private function moneyLabel(float $amount, string $currency): string
    {
        return $currency.' '.number_format($amount, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderInfo(VendorOrderMapping $mapping): array
    {
        $order = $mapping->order;

        return [
            'order_date' => $this->formatDisplayDateTime($order?->created_at) ?? '—',
            'delivery_date' => $this->formatDisplayDateTime($order?->estimated_arrival) ?? '—',
            'tracking' => $this->displayOrDash($mapping->tracking_number),
            'placed_at' => $this->formatDateTime($order?->created_at),
            'estimated_delivery' => $this->formatDate($order?->estimated_arrival),
            'payment_reference' => $order?->payment_reference,
            'transaction_id' => $order?->transaction_id,
        ];
    }

    /**
     * @return array{key: string, step: string, label: string, icon: string, color: string, status: string, completed: bool, current: bool, date: ?string, date_display: string}
     */
    private function timelineStep(
        VendorOrderStatus $status,
        string $stepStatus,
        mixed $date,
        bool $completed,
        bool $current
    ): array {
        $color = match ($stepStatus) {
            'completed' => 'gold',
            'current' => $status->color(),
            'cancelled' => 'red',
            default => 'grey',
        };

        return [
            'key' => $status->value,
            'step' => $status->value,
            'label' => $status->label(),
            'icon' => $status->icon(),
            'color' => $color,
            'status' => $stepStatus,
            'completed' => $completed,
            'current' => $current,
            'date' => $this->formatDateTime($date),
            'date_display' => $this->formatDisplayDateTime($date) ?? '—',
        ];
    }

    /**
     * @return array{key: string, label: string, icon: string, color: string, description: string, completed: bool, current: bool, timestamp: ?string, date: ?string, date_display: string}
     */
    private function trackTimelineStep(
        string $key,
        string $label,
        string $icon,
        string $color,
        string $description,
        mixed $date,
        bool $completed,
        bool $current,
        ?string $timestampOverride = null
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'description' => $description,
            'completed' => $completed,
            'current' => $current,
            'timestamp' => $timestampOverride ?? ($date !== null ? $this->formatTime($date) : null),
            'date' => $this->formatDateTime($date),
            'date_display' => $this->formatDisplayDateTime($date) ?? '—',
        ];
    }
}
