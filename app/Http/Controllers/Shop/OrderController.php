<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\WalletCredit;
use App\Notifications\AdminNotification;
use App\Services\ShopOrderCancellationService;
use App\Support\OrderClientReportService;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $isAdminLike = $this->userIsAdminLike($user);
        $relations = ['items.product'];
        if ($isAdminLike) {
            $relations[] = 'user';
        }

        $query = Order::query()
            ->with($relations)
            ->latest();

        if (! $isAdminLike) {
            $userEmail = strtolower(trim((string) ($user->email ?? '')));
            $query->where(function ($q) use ($user, $userEmail) {
                $q->where('user_id', $user->id);
                if ($userEmail !== '') {
                    $q->orWhere(function ($guest) use ($userEmail) {
                        $guest->whereNull('user_id')
                            ->whereRaw('LOWER(TRIM(guest_email)) = ?', [$userEmail]);
                    });
                }
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('order_status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 50);
        $orders = $query->paginate($perPage);
        $orders->setCollection(
            $orders->getCollection()->map(fn (Order $order) => $this->mapOrderForListApi($order))
        );

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with(['items.product.category', 'user', 'transactions', 'shippingAddress'])->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (! $this->canViewOrder($user, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order,
            'order_number' => $order->publicOrderNumber(),
            'order_number_short' => $order->publicOrderNumberDigits(),
            'order_summary' => $this->orderSummaryForApi($order),
        ], 200);
    }

    public function markPaid(Request $request, $id)
    {
        $order = Order::with('user')->find($id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $oldPaymentStatus = $order->payment_status;
        $order->payment_status = 'paid';
        $order->order_status = 'paid';
        $order->paid_at = now();
        $order->save();

        // 🔔 Notify client when order is paid
        try {
            if ($oldPaymentStatus !== 'paid' && $order->user) {
                $order->user->notify(new AdminNotification(
                    'Order Payment Confirmed',
                    "Your order #{$order->id} payment has been confirmed. Amount: AED {$order->total_amount}."
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send payment confirmation notification: '.$e->getMessage());
        }

        return response()->json(['status' => true, 'data' => $order], 200);
    }

    /**
     * Update order
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Check if user owns the order or is admin
        if (! $this->canViewOrder($user, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $rules = [
            'order_status' => 'nullable|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded',
            'shipping_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'special_instructions' => 'nullable|string|max:2000',
        ];
        if ($user->hasRole('admin') || $user->hasRole('supervisor') || $user->hasRole('area_manager')) {
            $rules['estimated_arrival'] = 'nullable|string|max:255';
            $rules['job_duration'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        $order->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->load(['items.product', 'user']),
        ], 200);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $cancellation = app(ShopOrderCancellationService::class);
        if ($cancellation->isForbidden((string) ($order->order_status ?? 'pending'))) {
            return response()->json([
                'success' => false,
                'message' => $cancellation->forbiddenMessage((string) ($order->order_status ?? 'pending')),
            ], 422);
        }

        $result = $cancellation->cancelOrder($order);
        $order->refresh();

        // 🔔 Notify admin and client about cancellation
        try {
            // Notify admins
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'Order Cancelled',
                    "Order #{$order->id} has been cancelled by {$user->name}."
                ));
            }

            // Notify client (if different from user who cancelled)
            if ($order->user && $order->user_id !== $user->id) {
                $order->user->notify(new AdminNotification(
                    'Order Cancelled',
                    "Your order #{$order->id} has been cancelled."
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send order cancellation notification: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => $order->load(['items.product', 'user']),
            'refund' => $result,
        ], 200);
    }

    /**
     * Track order – timeline UI (Pending → Confirmed → Assigned → In Progress → Completed → Delivered).
     * Returns order, timeline steps with label/description/timestamp/completed, and maintenance_photos.
     */
    public function track(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with(['items.product', 'items.product.primaryImage', 'user', 'shippingAddress'])->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $timeline = $this->buildOrderTimeline($order);
        $maintenancePhotos = $this->getOrderMaintenancePhotos($order);
        $serviceReport = app(OrderClientReportService::class)->serviceReportMetaForOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Order tracking information retrieved successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->publicOrderNumber(),
                'order_number_short' => $order->publicOrderNumberDigits(),
                'order' => $this->mapOrderForTrackApi($order),
                'order_summary' => $this->orderSummaryForApi($order),
                'current_status' => $this->mapOrderStatusToLabel($order->order_status),
                'tracking' => [
                    'status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'timeline' => $timeline,
                    'created_at' => $order->created_at?->format('c'),
                    'updated_at' => $order->updated_at?->format('c'),
                    'paid_at' => $order->paid_at?->format('c'),
                ],
                'service_report' => $serviceReport,
                'service_rating' => $this->serviceRatingMetaForOrder($order, $user),
                'maintenance_photos' => $maintenancePhotos,
                'can_cancel' => ! app(ShopOrderCancellationService::class)->isForbidden((string) ($order->order_status ?? 'pending')),
                'refund_policy' => RefundPolicy::policyForApi(),
                'wallet' => $this->walletSnapshot($order),
            ],
        ], 200);
    }

    /**
     * Client service report for a shop order (visible only after supervisor submits to client).
     */
    public function report(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::query()->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (! $this->canViewOrder($user, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reportService = app(OrderClientReportService::class);
        $report = $reportService->findReportForOrder($order);

        if (! $reportService->isReportVisibleToClient($report)) {
            return response()->json([
                'success' => false,
                'message' => 'Service report is not available yet. It will appear after your supervisor submits it.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service report retrieved successfully.',
            'data' => $reportService->formatReportForClient($report),
        ], 200);
    }

    /**
     * Client confirms delivery after reviewing the service report.
     */
    public function markDelivered(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::query()->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->user_id !== null && (int) $order->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($order->user_id === null) {
            $orderEmail = strtolower(trim((string) ($order->guest_email ?? '')));
            $userEmail = strtolower(trim((string) ($user->email ?? '')));
            if ($orderEmail === '' || $orderEmail !== $userEmail) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        }

        $reportService = app(OrderClientReportService::class);
        $report = $reportService->findReportForOrder($order);
        if (! $reportService->isReportVisibleToClient($report)) {
            return response()->json([
                'success' => false,
                'message' => 'Service report must be available before marking the order as delivered.',
            ], 422);
        }

        $status = strtolower((string) ($order->order_status ?? 'pending'));
        if ($status === 'delivered') {
            return response()->json([
                'success' => true,
                'message' => 'Order is already marked as delivered.',
                'data' => [
                    'order_id' => $order->id,
                    'order_status' => $order->order_status,
                    'tracking' => [
                        'status' => $order->order_status,
                        'timeline' => $this->buildOrderTimeline($order),
                    ],
                ],
            ], 200);
        }

        if ($status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Order must be completed before it can be marked as delivered.',
            ], 422);
        }

        $order->order_status = 'delivered';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order marked as delivered.',
            'data' => [
                'order_id' => $order->id,
                'order_status' => $order->order_status,
                'current_status' => $this->mapOrderStatusToLabel($order->order_status),
                'tracking' => [
                    'status' => $order->order_status,
                    'timeline' => $this->buildOrderTimeline($order),
                ],
                'service_report' => $reportService->serviceReportMetaForOrder($order),
            ],
        ], 200);
    }

    /**
     * Cancelled orders list for app cancelled tab.
     */
    public function cancelledList(Request $request)
    {
        $user = $request->user();
        $query = Order::query()
            ->with(['items.product', 'user'])
            ->where('order_status', 'cancelled')
            ->latest();

        $roleValue = strtolower(trim((string) ($user->role ?? '')));
        $isAdminLike = in_array($roleValue, ['admin', 'supervisor', 'area_manager'], true);
        if (! $isAdminLike && method_exists($user, 'getRoleNames')) {
            try {
                $roleNames = collect($user->getRoleNames())->map(fn ($r) => strtolower((string) $r));
                $isAdminLike = $roleNames->intersect(['admin', 'supervisor', 'area_manager'])->isNotEmpty();
            } catch (\Throwable $e) {
                // keep fallback path non-fatal
            }
        }
        if (! $isAdminLike && method_exists($user, 'hasRole')) {
            $isAdminLike = $user->hasRole('admin')
                || $user->hasRole('supervisor')
                || $user->hasRole('area_manager');
        }

        if (! $isAdminLike) {
            $userEmail = strtolower(trim((string) ($user->email ?? '')));
            $query->where(function ($q) use ($user, $userEmail) {
                $q->where('user_id', $user->id);
                if ($userEmail !== '') {
                    $q->orWhere(function ($guest) use ($userEmail) {
                        $guest->whereNull('user_id')
                            ->whereRaw('LOWER(TRIM(guest_email)) = ?', [$userEmail]);
                    });
                }
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Cancelled orders retrieved successfully',
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], 200);
    }

    /**
     * Dedicated cancelled-order tracking detail (for cancelled card click flow).
     */
    public function cancelTrack(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with(['items.product', 'items.product.primaryImage', 'user', 'shippingAddress'])->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (! $this->canViewOrder($user, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if (strtolower((string) ($order->order_status ?? '')) !== 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This order is not cancelled.',
            ], 422);
        }

        $timeline = $this->buildCancelledOrderTimeline($order);
        $maintenancePhotos = $this->getOrderMaintenancePhotos($order);

        return response()->json([
            'success' => true,
            'message' => 'Cancelled order tracking retrieved successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->publicOrderNumber(),
                'order_number_short' => $order->publicOrderNumberDigits(),
                'order' => $this->mapOrderForTrackApi($order),
                'order_summary' => $this->orderSummaryForApi($order),
                'current_status' => $this->mapOrderStatusToLabel($order->order_status),
                'tracking' => [
                    'status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'timeline' => $timeline,
                    'created_at' => $order->created_at?->format('c'),
                    'updated_at' => $order->updated_at?->format('c'),
                    'paid_at' => $order->paid_at?->format('c'),
                ],
                'maintenance_photos' => $maintenancePhotos,
                'can_cancel' => false,
                'refund_policy' => RefundPolicy::policyForApi(),
                'wallet' => $this->walletSnapshot($order),
            ],
        ], 200);
    }

    /**
     * Guest: get order details by order_number + email (no auth). For guest checkout tracking.
     */
    public function guestShow(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string|max:50',
            'email' => 'required|email',
        ]);
        $order = $this->findGuestOrder($request->input('order_number'), $request->input('email'));
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found or email does not match.'], 404);
        }
        $order->load(['items.product', 'items.product.primaryImage']);
        $data = $order->toArray();
        $data['shipping_address'] = $order->getShippingAddressForApi();
        $data['order_number'] = $order->publicOrderNumber();

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved.',
            'data' => $data,
            'order_number' => $order->publicOrderNumber(),
            'order_number_short' => $order->publicOrderNumberDigits(),
            'order_summary' => $this->orderSummaryForApi($order),
        ], 200);
    }

    /**
     * Guest: get order tracking by order_number + email (no auth). Same structure as track().
     */
    public function guestTrack(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string|max:50',
            'email' => 'required|email',
        ]);
        $order = $this->findGuestOrder($request->input('order_number'), $request->input('email'));
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found or email does not match.'], 404);
        }
        $order->load(['items.product', 'items.product.primaryImage', 'shippingAddress']);
        $timeline = $this->buildOrderTimeline($order);
        $maintenancePhotos = $this->getOrderMaintenancePhotos($order);
        $serviceReport = app(OrderClientReportService::class)->serviceReportMetaForOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Order tracking information retrieved successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->publicOrderNumber(),
                'order_number_short' => $order->publicOrderNumberDigits(),
                'order' => $this->mapOrderForTrackApi($order),
                'order_summary' => $this->orderSummaryForApi($order),
                'current_status' => $this->mapOrderStatusToLabel($order->order_status),
                'tracking' => [
                    'status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'timeline' => $timeline,
                    'created_at' => $order->created_at?->format('c'),
                    'updated_at' => $order->updated_at?->format('c'),
                    'paid_at' => $order->paid_at?->format('c'),
                ],
                'service_report' => $serviceReport,
                'service_rating' => $this->serviceRatingMetaForOrder($order, $request->user()),
                'maintenance_photos' => $maintenancePhotos,
                'can_cancel' => ! app(ShopOrderCancellationService::class)->isForbidden((string) ($order->order_status ?? 'pending')),
                'refund_policy' => RefundPolicy::policyForApi(),
                'wallet' => $this->walletSnapshot($order),
            ],
        ], 200);
    }

    /**
     * Resolve guest order by order_number (e.g. order_0001) and guest_email.
     */
    private function findGuestOrder(string $orderNumber, string $email): ?Order
    {
        $id = null;
        if (preg_match('/^order_(\d+)$/i', trim($orderNumber), $m)) {
            $id = (int) $m[1];
        } elseif (is_numeric(trim($orderNumber))) {
            $id = (int) $orderNumber;
        }
        if ($id === null) {
            return null;
        }

        return Order::whereNull('user_id')
            ->where('id', $id)
            ->where('guest_email', $email)
            ->first();
    }

    /**
     * Build timeline steps for order tracking UI (Pending → Delivered).
     */
    private function buildOrderTimeline(Order $order): array
    {
        $status = $this->normalizeOrderTrackingStatus((string) ($order->order_status ?? 'pending'));
        $rank = $this->orderTrackingRank($status);
        $createdAt = $order->created_at;
        $updatedAt = $order->updated_at;
        $paidAt = $order->paid_at;

        $steps = [
            ['key' => 'pending', 'label' => 'Pending', 'description' => 'Order placed successfully', 'completed' => true, 'timestamp' => $createdAt?->format('g:i A') ?? null],
            ['key' => 'confirmed', 'label' => 'Confirmed', 'description' => 'Order confirmed by our team', 'completed' => $rank >= $this->orderTrackingRank('confirmed'), 'timestamp' => $rank >= $this->orderTrackingRank('confirmed') ? ($paidAt ?? $updatedAt)?->format('g:i A') : null],
            ['key' => 'assigned', 'label' => 'Assigned', 'description' => 'Technician assigned to your order', 'completed' => $rank >= $this->orderTrackingRank('assigned'), 'timestamp' => $rank >= $this->orderTrackingRank('assigned') ? $updatedAt?->format('g:i A') : null],
            ['key' => 'in_progress', 'label' => 'In Progress', 'description' => 'Your order is being processed', 'completed' => $rank >= $this->orderTrackingRank('in_progress'), 'timestamp' => $rank >= $this->orderTrackingRank('in_progress') ? $updatedAt?->format('g:i A') : null],
            ['key' => 'completed', 'label' => 'Completed', 'description' => 'Your order is ready!', 'completed' => $rank >= $this->orderTrackingRank('completed'), 'timestamp' => $rank >= $this->orderTrackingRank('completed') ? $updatedAt?->format('g:i A') : null],
            ['key' => 'delivered', 'label' => 'Delivered', 'description' => 'Delivered', 'completed' => $rank >= $this->orderTrackingRank('delivered'), 'timestamp' => $rank >= $this->orderTrackingRank('delivered') ? $updatedAt?->format('g:i A') : null],
        ];

        return $steps;
    }

    /**
     * Build timeline for cancelled-order tracking.
     */
    private function buildCancelledOrderTimeline(Order $order): array
    {
        $createdAt = $order->created_at;
        $cancelledAt = $order->updated_at;
        $isRefunded = strtolower((string) ($order->payment_status ?? '')) === 'refunded';
        $hasRefund = (float) ($order->refund_amount ?? 0) > 0;
        $refundProcessing = $hasRefund && ! $isRefunded;

        $steps = [
            [
                'key' => 'pending',
                'label' => 'Pending',
                'description' => 'Order placed successfully',
                'completed' => true,
                'timestamp' => $createdAt?->format('g:i A') ?? null,
            ],
            [
                'key' => 'cancel_order',
                'label' => 'Cancel order',
                'description' => 'Order cancelled by customer request',
                'completed' => true,
                'timestamp' => $cancelledAt?->format('g:i A') ?? null,
            ],
        ];

        if ($refundProcessing || $isRefunded) {
            $steps[] = [
                'key' => 'refund_processing',
                'label' => 'Refund Processing',
                'description' => 'Refund request is being processed',
                'completed' => $isRefunded,
                'timestamp' => $isRefunded ? ($order->refunded_at?->format('g:i A') ?? $cancelledAt?->format('g:i A')) : null,
            ];
        }

        if ($isRefunded) {
            $steps[] = [
                'key' => 'refund_complete',
                'label' => 'Refund complete',
                'description' => 'Refund amount credited back to original payment method',
                'completed' => true,
                'timestamp' => $order->refunded_at?->format('g:i A') ?? $cancelledAt?->format('g:i A'),
            ];
        }

        return $steps;
    }

    /**
     * One-screen order summary (date, address, payment, total, special instructions).
     */
    private function orderSummaryForApi(Order $order): array
    {
        $currency = strtoupper((string) config('shop.currency', 'AED'));

        $addr = $order->payerAddressForDisplay();
        $deliveryLine = $addr !== ''
            ? preg_replace("/\s*\n\s*/", ', ', trim($addr))
            : null;

        $order->loadMissing('items.product');
        $firstProduct = $order->items->first()?->product;

        /*
        |--------------------------------------------------------------------------
        | Timing
        |--------------------------------------------------------------------------
        */

        $estimatedArrival = $order->estimated_arrival;

        if (($estimatedArrival === null || $estimatedArrival === '') && $firstProduct) {
            $estimatedArrival = $firstProduct->estimated_arrival;
        }

        $jobDuration = $order->job_duration;

        if (($jobDuration === null || $jobDuration === '') && $firstProduct) {
            $jobDuration = $firstProduct->job_duration;
        }

        /*
        |--------------------------------------------------------------------------
        | Booking Date & Slot
        |--------------------------------------------------------------------------
        |
        | Prefer order-level booking_date / booking_slot. When those are empty
        | (cart checkout stores slots on order_items only), derive from items:
        | - 1 item → that item's slot
        | - many items with the same date/slot → that shared value
        | - mixed slots → first item (per-product truth remains on order.items[])
        |
        */

        $bookingDate = $order->booking_date
            ? \Carbon\Carbon::parse($order->booking_date)->format('Y-m-d')
            : null;

        $bookingSlot = $order->booking_slot;
        if (is_string($bookingSlot) && trim($bookingSlot) === '') {
            $bookingSlot = null;
        }

        if ($bookingDate === null || $bookingSlot === null) {
            $order->loadMissing('items');
            $itemDates = $order->items
                ->map(fn ($item) => $item->booking_date?->toDateString())
                ->filter()
                ->values();
            $itemSlots = $order->items
                ->map(fn ($item) => is_string($item->booking_slot) ? trim($item->booking_slot) : null)
                ->filter()
                ->values();

            if ($bookingDate === null && $itemDates->isNotEmpty()) {
                $uniqueDates = $itemDates->unique()->values();
                $bookingDate = $uniqueDates->count() === 1
                    ? $uniqueDates->first()
                    : $itemDates->first();
            }

            if ($bookingSlot === null && $itemSlots->isNotEmpty()) {
                $uniqueSlots = $itemSlots->unique()->values();
                $bookingSlot = $uniqueSlots->count() === 1
                    ? $uniqueSlots->first()
                    : $itemSlots->first();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Try to extract start/end time from booking_slot
        |--------------------------------------------------------------------------
        |
        | Supported examples:
        |
        | "10:00 AM - 12:00 PM"
        | "10:00 - 12:00"
        | "10:00 AM to 12:00 PM"
        | "09:00 AM" (start only)
        |
        */

        $slotStartTime = null;
        $slotEndTime = null;

        if (! empty($bookingSlot)) {
            $slot = trim((string) $bookingSlot);

            // Example: 10:00 AM - 12:00 PM
            if (preg_match(
                '/^\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*(?:-|–|—|to)\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*$/i',
                $slot,
                $matches
            )) {
                $slotStartTime = trim($matches[1]);
                $slotEndTime = trim($matches[2]);
            } elseif (preg_match('/^\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*$/i', $slot, $matches)) {
                $slotStartTime = trim($matches[1]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */

        $paymentLabel = match (strtolower((string) ($order->payment_method ?? ''))) {
            'stripe' => 'Credit card',
            'paypal' => 'PayPal',
            default => $order->paymentMethodLabel(),
        };

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return [
            'placed_at' => $order->created_at?->format('c'),

            // Booking information
            'booking_date' => $bookingDate,
            'booking_slot' => $bookingSlot,

            // Parsed slot times
            'slot_start_time' => $slotStartTime,
            'slot_end_time' => $slotEndTime,

            'delivery_address' => $deliveryLine,

            'payment_method' => $paymentLabel,
            'payment_method_code' => $order->payment_method,

            'total' => (float) $order->total_amount,
            'currency' => $currency,

            'payment_status' => $order->payment_status,

            'refund_amount' => (float) ($order->refund_amount ?? 0),
            'refund_reason' => $order->refund_reason,
            'refunded_at' => $order->refunded_at?->format('c'),

            'special_instructions' => $order->special_instructions,

            'estimated_arrival' => $estimatedArrival,
            'job_duration' => $jobDuration,
        ];
    }

    private function mapOrderStatusToLabel(?string $status): string
    {
        $status = $this->normalizeOrderTrackingStatus((string) ($status ?? 'pending'));
        $map = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        return $map[$status ?? ''] ?? ucfirst($status ?? 'Pending');
    }

    private function normalizeOrderTrackingStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'paid' => 'confirmed',
            'processing' => 'in_progress',
            'shipped' => 'completed',
            'pending', 'confirmed', 'assigned', 'in_progress', 'completed', 'delivered', 'cancelled' => $status,
            default => $status !== '' ? $status : 'pending',
        };
    }

    private function orderTrackingRank(string $status): int
    {
        return match ($status) {
            'pending' => 0,
            'confirmed' => 1,
            'assigned' => 2,
            'in_progress' => 3,
            'completed' => 4,
            'delivered' => 5,
            default => 0,
        };
    }

    /**
     * Maintenance photos linked to this order (e.g. from visits). Placeholder: empty until order–visit link exists.
     */
    private function getOrderMaintenancePhotos(Order $order): array
    {
        $reportService = app(OrderClientReportService::class);
        $visit = $reportService->findVisitForOrder($order);
        if ($visit === null) {
            return [];
        }

        $photoService = app(\App\Services\VisitPhotoService::class);

        return $visit->photos()
            ->where('show_on_client_app', true)
            ->orderBy('id')
            ->get()
            ->map(fn ($photo) => $photoService->toApiItem($photo))
            ->values()
            ->all();
    }

    /**
     * View permission for order detail/track:
     * - admin can view all
     * - owner (order.user_id) can view
     * - guest order can be viewed by authenticated user with same email
     */
    private function canViewOrder(User $user, Order $order): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($order->user_id !== null) {
            return (int) $order->user_id === (int) $user->id;
        }

        $orderEmail = strtolower(trim((string) ($order->guest_email ?? '')));
        $userEmail = strtolower(trim((string) ($user->email ?? '')));

        return $orderEmail !== '' && $orderEmail === $userEmail;
    }

    /**
     * Rate the service for a completed/delivered order.
     *
     * Body:
     *   rating (int 1-5, required)         – overall service rating
     *   review (string, optional)          – service review text
     *   product_ratings[] (optional)       – per-product ratings:
     *       [{ product_id, rating (1-5), review? }]
     *       When omitted, the service rating is applied to every product in the order.
     *
     * Stores an order-level service review (product_id = null) plus one review per product,
     * and recomputes each product's rating_average / rating_count.
     */
    public function rate(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items')->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (! $this->canViewOrder($user, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $status = strtolower((string) ($order->order_status ?? 'pending'));
        if (! in_array($status, ['completed', 'delivered'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'You can rate the service once the order is completed.',
            ], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'product_ratings' => 'nullable|array',
            'product_ratings.*.product_id' => 'required|integer',
            'product_ratings.*.rating' => 'required|integer|min:1|max:5',
            'product_ratings.*.review' => 'nullable|string|max:1000',
        ]);

        $orderProductIds = $order->items->pluck('product_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();

        // Build per-product rating map (product_id => ['rating' => int, 'review' => ?string]).
        $productRatings = [];
        if (! empty($validated['product_ratings'])) {
            foreach ($validated['product_ratings'] as $entry) {
                $pid = (int) $entry['product_id'];
                if (! $orderProductIds->contains($pid)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product #{$pid} is not part of this order.",
                    ], 422);
                }
                $productRatings[$pid] = [
                    'rating' => (int) $entry['rating'],
                    'review' => $entry['review'] ?? null,
                ];
            }
        } else {
            // Default: apply the overall service rating/review to every product in the order.
            foreach ($orderProductIds as $pid) {
                $productRatings[$pid] = [
                    'rating' => (int) $validated['rating'],
                    'review' => $validated['review'] ?? null,
                ];
            }
        }

        \DB::transaction(function () use ($order, $user, $validated, $productRatings) {
            // Service (order-level) review — product_id null.
            Review::updateOrCreate(
                ['user_id' => $user->id, 'order_id' => $order->id, 'product_id' => null],
                ['rating' => (int) $validated['rating'], 'comment' => $validated['review'] ?? null]
            );

            foreach ($productRatings as $pid => $data) {
                Review::updateOrCreate(
                    ['user_id' => $user->id, 'order_id' => $order->id, 'product_id' => $pid],
                    ['rating' => $data['rating'], 'comment' => $data['review']]
                );
            }
        });

        // Recompute aggregate rating for each rated product.
        $productSnapshots = [];
        foreach (Product::whereIn('id', array_keys($productRatings))->get() as $product) {
            $product->recalculateRating();
            $productSnapshots[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'rating' => $productRatings[$product->id]['rating'],
                'review' => $productRatings[$product->id]['review'],
                'rating_average' => (float) $product->rating_average,
                'rating_count' => (int) $product->rating_count,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your rating has been submitted.',
            'data' => [
                'order_id' => $order->id,
                'service_rating' => (int) $validated['rating'],
                'service_review' => $validated['review'] ?? null,
                'product_ratings' => $productSnapshots,
            ],
        ], 200);
    }

    /**
     * Existing rating for an order (so the app can show already-submitted state).
     */
    public function rating(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::query()->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (! $this->canViewOrder($user, $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order rating retrieved successfully.',
            'data' => $this->serviceRatingMetaForOrder($order, $user),
        ], 200);
    }

    /**
     * Rating meta for track/rating responses: whether the client can rate and any existing rating.
     *
     * @return array<string, mixed>
     */
    private function serviceRatingMetaForOrder(Order $order, ?User $user): array
    {
        $status = strtolower((string) ($order->order_status ?? 'pending'));
        $canRate = in_array($status, ['completed', 'delivered'], true);

        $serviceReview = null;
        $productReviews = [];

        if ($user !== null) {
            $reviews = Review::query()
                ->where('order_id', $order->id)
                ->where('user_id', $user->id)
                ->get();

            $service = $reviews->firstWhere('product_id', null);
            if ($service) {
                $serviceReview = [
                    'rating' => (int) $service->rating,
                    'review' => $service->comment,
                    'submitted_at' => $service->created_at?->format('c'),
                ];
            }

            $productReviews = $reviews->whereNotNull('product_id')->map(fn (Review $r) => [
                'product_id' => (int) $r->product_id,
                'rating' => (int) $r->rating,
                'review' => $r->comment,
            ])->values()->all();
        }

        return [
            'can_rate' => $canRate,
            'has_rated' => $serviceReview !== null,
            'service_rating' => $serviceReview,
            'product_ratings' => $productReviews,
        ];
    }

    private function userIsAdminLike(User $user): bool
    {
        $roleValue = strtolower(trim((string) ($user->role ?? '')));

        return in_array($roleValue, ['admin', 'supervisor', 'area_manager'], true);
    }

    private function walletCreditsTableExists(): bool
    {
        try {
            return Schema::hasTable('wallet_credits');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function walletSnapshot(Order $order): ?array
    {
        if (! $order->user_id || ! $order->relationLoaded('user') && ! $order->user) {
            return null;
        }
        if (! $this->walletCreditsTableExists()) {
            return [
                'balance' => (float) (($order->user?->wallet_balance) ?? 0),
                'last_refund_credit' => null,
            ];
        }
        $user = $order->user;
        if (! $user) {
            return null;
        }

        try {
            $latestCredit = WalletCredit::query()
                ->where('user_id', $user->id)
                ->where('order_id', $order->id)
                ->latest('id')
                ->first();
        } catch (\Throwable $e) {
            return [
                'balance' => (float) ($user->wallet_balance ?? 0),
                'last_refund_credit' => null,
            ];
        }

        return [
            'balance' => (float) ($user->wallet_balance ?? 0),
            'last_refund_credit' => $latestCredit ? [
                'amount' => (float) $latestCredit->amount,
                'status' => $latestCredit->status,
                'expires_at' => $latestCredit->expires_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function resolveOrderTiming(Order $order): array
    {
        $firstProduct = $order->items->first()?->product;
        $estimatedArrival = $order->estimated_arrival;
        if (($estimatedArrival === null || $estimatedArrival === '') && $firstProduct) {
            $estimatedArrival = $firstProduct->estimated_arrival;
        }

        $jobDuration = $order->job_duration;
        if (($jobDuration === null || $jobDuration === '') && $firstProduct) {
            $jobDuration = $firstProduct->job_duration;
        }

        return [$estimatedArrival, $jobDuration];
    }

    private function mapOrderItemProductForApi(?OrderItem $item): ?array
    {
        $product = $item?->product;
        if (! $product) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'image_url' => $product->image_url,
            'job_duration' => $product->job_duration,
            'estimated_arrival' => $product->estimated_arrival,
        ];
    }

    private function mapOrderForListApi(Order $order): array
    {
        [$estimatedArrival, $jobDuration] = $this->resolveOrderTiming($order);

        return [
            'id' => $order->id,
            'order_number' => $order->publicOrderNumber(),
            'order_number_short' => $order->publicOrderNumberDigits(),
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total_amount' => (float) $order->total_amount,
            'subtotal_amount' => (float) ($order->subtotal_amount ?? 0),
            'shipping_amount' => (float) ($order->shipping_amount ?? 0),
            'tax_amount' => (float) ($order->tax_amount ?? 0),
            'currency' => strtoupper((string) config('shop.currency', 'AED')),
            'special_instructions' => $order->special_instructions,
            'estimated_arrival' => $estimatedArrival,
            'job_duration' => $jobDuration,
            'created_at' => $order->created_at?->format('c'),
            'paid_at' => $order->paid_at?->format('c'),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
                'booking_date' => $item->booking_date?->toDateString(),
                'booking_slot' => $item->booking_slot,
                'product' => $this->mapOrderItemProductForApi($item),
            ])->values()->all(),
        ];
    }

    private function mapOrderForTrackApi(Order $order): array
    {
        [$estimatedArrival, $jobDuration] = $this->resolveOrderTiming($order);

        return [
            'id' => $order->id,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total_amount' => (float) $order->total_amount,
            'subtotal_amount' => (float) ($order->subtotal_amount ?? 0),
            'shipping_amount' => (float) ($order->shipping_amount ?? 0),
            'tax_amount' => (float) ($order->tax_amount ?? 0),
            'special_instructions' => $order->special_instructions,
            'estimated_arrival' => $estimatedArrival,
            'job_duration' => $jobDuration,
            'created_at' => $order->created_at?->format('c'),
            'updated_at' => $order->updated_at?->format('c'),
            'paid_at' => $order->paid_at?->format('c'),
            'shipping_address' => $order->getShippingAddressForApi(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
                'booking_date' => $item->booking_date?->toDateString(),
                'booking_slot' => $item->booking_slot,
                'product' => $this->mapOrderItemProductForApi($item),
            ])->values()->all(),
        ];
    }
}
