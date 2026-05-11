<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use App\Notifications\AdminNotification;
use App\Services\ShopOrderCancellationService;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::query()
            ->with(['items.product', 'user'])
            ->latest();

        $roleValue = strtolower(trim((string) ($user->role ?? '')));
        $isAdminLike = in_array($roleValue, ['admin', 'supervisor', 'area_manager'], true)
            || $user->hasRole('admin')
            || $user->hasRole('supervisor')
            || $user->hasRole('area_manager');
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

        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);
        $orders->setCollection(
            $orders->getCollection()->map(function (Order $order) {
                $firstProduct = $order->items->first()?->product;
                if (($order->estimated_arrival === null || $order->estimated_arrival === '') && $firstProduct) {
                    $order->estimated_arrival = $firstProduct->estimated_arrival;
                }
                if (($order->job_duration === null || $order->job_duration === '') && $firstProduct) {
                    $order->job_duration = $firstProduct->job_duration;
                }

                return $order;
            })
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

        $orderArray = $order->toArray();
        $orderArray['shipping_address'] = $order->getShippingAddressForApi();

        return response()->json([
            'success' => true,
            'message' => 'Order tracking information retrieved successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->publicOrderNumber(),
                'order_number_short' => $order->publicOrderNumberDigits(),
                'order' => $orderArray,
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
                'can_cancel' => ! app(ShopOrderCancellationService::class)->isForbidden((string) ($order->order_status ?? 'pending')),
                'refund_policy' => RefundPolicy::policyForApi(),
                'wallet' => $this->walletSnapshot($order),
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
        $orderArray = $order->toArray();
        $orderArray['shipping_address'] = $order->getShippingAddressForApi();

        return response()->json([
            'success' => true,
            'message' => 'Cancelled order tracking retrieved successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->publicOrderNumber(),
                'order_number_short' => $order->publicOrderNumberDigits(),
                'order' => $orderArray,
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
        $orderArray = $order->toArray();
        $orderArray['shipping_address'] = $order->getShippingAddressForApi();

        return response()->json([
            'success' => true,
            'message' => 'Order tracking information retrieved successfully',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->publicOrderNumber(),
                'order_number_short' => $order->publicOrderNumberDigits(),
                'order' => $orderArray,
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
                'can_cancel' => ! app(ShopOrderCancellationService::class)->isForbidden((string) ($order->order_status ?? 'pending')),
                'refund_policy' => RefundPolicy::policyForApi(),
                'wallet' => $this->walletSnapshot($order),
            ],
        ], 200);
    }

    private function walletSnapshot(Order $order): ?array
    {
        if (! $order->user_id || ! $order->relationLoaded('user') && ! $order->user) {
            return null;
        }
        // Some deployments may be behind on wallet migrations; keep tracking API non-fatal.
        if (! Schema::hasTable('wallet_credits')) {
            return [
                'balance' => (float) (($order->user?->wallet_balance) ?? 0),
                'last_refund_credit' => null,
            ];
        }
        $user = $order->user;
        if (! $user) {
            return null;
        }

        $latestCredit = WalletCredit::query()
            ->where('user_id', $user->id)
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        return [
            'balance' => (float) ($user->wallet_balance ?? 0),
            'last_refund_credit' => $latestCredit ? [
                'amount' => (float) $latestCredit->amount,
                'status' => $latestCredit->status,
                'expires_at' => $latestCredit->expires_at?->toIso8601String(),
            ] : null,
        ];
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
        $deliveryLine = $addr !== '' ? preg_replace("/\s*\n\s*/", ', ', trim($addr)) : null;
        $order->loadMissing('items.product');
        $firstProduct = $order->items->first()?->product;

        $estimatedArrival = $order->estimated_arrival;
        if (($estimatedArrival === null || $estimatedArrival === '') && $firstProduct) {
            $estimatedArrival = $firstProduct->estimated_arrival;
        }

        $jobDuration = $order->job_duration;
        if (($jobDuration === null || $jobDuration === '') && $firstProduct) {
            $jobDuration = $firstProduct->job_duration;
        }

        $paymentLabel = match (strtolower((string) ($order->payment_method ?? ''))) {
            'stripe' => 'Credit card',
            'paypal' => 'PayPal',
            default => $order->paymentMethodLabel(),
        };

        return [
            'placed_at' => $order->created_at?->format('c'),
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
        $photos = [];

        // TODO: when orders are linked to visits, load VisitPhoto or MaintenancePhoto and return image_url
        return $photos;
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
     * Rate order
     */
    public function rate(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Check if user owns the order
        if ($order->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        // Store rating in notes field if rating/review columns don't exist
        // This is a workaround until proper rating columns are added
        $notes = $order->notes ?? '';
        $ratingNote = "Rating: {$validated['rating']}/5";
        if (! empty($validated['review'])) {
            $ratingNote .= "\nReview: {$validated['review']}";
        }

        // Try to update rating if column exists, otherwise store in notes
        $updateData = [];
        if (in_array('rating', $order->getFillable()) || \Schema::hasColumn('orders', 'rating')) {
            $updateData['rating'] = $validated['rating'];
        }
        if (in_array('review', $order->getFillable()) || \Schema::hasColumn('orders', 'review')) {
            $updateData['review'] = $validated['review'] ?? null;
        }
        if (in_array('notes', $order->getFillable()) || \Schema::hasColumn('orders', 'notes')) {
            $updateData['notes'] = $notes.($notes ? "\n\n" : '').$ratingNote;
        }

        if (! empty($updateData)) {
            $order->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order rated successfully',
            'data' => [
                'order' => $order->load(['items.product', 'user']),
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ],
        ], 200);
    }
}
