<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use App\Notifications\AdminNotification;
use App\Support\RefundPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Only allow cancellation if order is not already delivered or cancelled
        if (in_array($order->order_status, ['delivered', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel order with status: '.$order->order_status,
            ], 400);
        }

        $result = $this->cancelWithPolicy($order);
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
                'can_cancel' => ! in_array($order->order_status, ['delivered', 'cancelled']),
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
                'can_cancel' => ! in_array($order->order_status, ['delivered', 'cancelled']),
                'refund_policy' => RefundPolicy::policyForApi(),
                'wallet' => $this->walletSnapshot($order),
            ],
        ], 200);
    }

    /**
     * Guest: cancel order by order_number + email (same verification as track).
     */
    public function guestCancel(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string|max:50',
            'email' => 'required|email',
        ]);

        $order = $this->findGuestOrder($request->input('order_number'), $request->input('email'));
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found or email does not match.'], 404);
        }

        if (in_array($order->order_status, ['delivered', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel order with status: '.$order->order_status,
            ], 400);
        }

        $result = $this->cancelWithPolicy($order);
        $order->refresh();

        try {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'Order Cancelled',
                    'Guest order #'.$order->id.' ('.$order->guest_email.') was cancelled.'
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send order cancellation notification: '.$e->getMessage());
        }

        $order->load(['items.product', 'shippingAddress']);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => [
                'order' => $order,
                'order_number' => $order->publicOrderNumber(),
                'order_number_short' => $order->publicOrderNumberDigits(),
                'order_summary' => $this->orderSummaryForApi($order),
            ],
            'refund' => $result,
        ], 200);
    }

    /**
     * Apply timeline-based cancellation + refund policy and wallet crediting.
     *
     * @return array{
     *   stage:string,
     *   refund_percent:float,
     *   refund_amount:float,
     *   service_fee_amount:float,
     *   wallet_credited:float,
     *   wallet_expires_at:?string
     * }
     */
    private function cancelWithPolicy(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $decision = RefundPolicy::decisionForOrder($locked);
            $isPaid = strtolower((string) $locked->payment_status) === 'paid';
            $total = (float) $locked->total_amount;
            $refundAmount = $isPaid ? round($total * ((float) $decision['percent'] / 100), 2) : 0.0;
            $serviceFeeAmount = $isPaid ? round(max(0, $total - $refundAmount), 2) : 0.0;

            $walletCredited = 0.0;
            $expiresAt = null;
            if ($refundAmount > 0 && $locked->user_id) {
                $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->first();
                if ($user) {
                    $months = RefundPolicy::walletValidityMonths();
                    $expiresAt = now()->addMonths($months);
                    $walletCredited = $refundAmount;
                    $user->wallet_balance = round((float) ($user->wallet_balance ?? 0) + $walletCredited, 2);
                    $user->save();

                    WalletCredit::create([
                        'user_id' => $user->id,
                        'order_id' => $locked->id,
                        'amount' => $walletCredited,
                        'reason' => 'order_refund',
                        'status' => 'active',
                        'credited_at' => now(),
                        'expires_at' => $expiresAt,
                    ]);
                }
            }

            $locked->order_status = 'cancelled';
            $locked->payment_status = $isPaid ? 'refunded' : 'pending';
            $locked->refund_amount = $refundAmount;
            $locked->refund_reason = (string) ($decision['reason'] ?? 'Refund policy applied');
            $locked->refunded_at = $refundAmount > 0 ? now() : null;
            $locked->save();

            return [
                'stage' => (string) ($decision['stage'] ?? 'fallback'),
                'refund_percent' => (float) ($decision['percent'] ?? 0),
                'refund_amount' => $refundAmount,
                'service_fee_amount' => $serviceFeeAmount,
                'wallet_credited' => $walletCredited,
                'wallet_expires_at' => $expiresAt?->toIso8601String(),
            ];
        });
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
        $status = $order->order_status ?? 'pending';
        $createdAt = $order->created_at;
        $updatedAt = $order->updated_at;
        $paidAt = $order->paid_at;

        $steps = [
            ['key' => 'pending', 'label' => 'Pending', 'description' => 'Order placed successfully', 'completed' => true, 'timestamp' => $createdAt?->format('g:i A') ?? null],
            ['key' => 'confirmed', 'label' => 'Confirmed', 'description' => 'Order confirmed by our team', 'completed' => in_array($status, ['processing', 'paid', 'shipped', 'delivered']), 'timestamp' => ($paidAt ?? $updatedAt)?->format('g:i A')],
            ['key' => 'assigned', 'label' => 'Assigned', 'description' => 'Technician assigned to your order', 'completed' => in_array($status, ['processing', 'paid', 'shipped', 'delivered']), 'timestamp' => in_array($status, ['processing', 'paid', 'shipped', 'delivered']) ? $updatedAt?->format('g:i A') : null],
            ['key' => 'in_progress', 'label' => 'In Progress', 'description' => 'Your order is being processed', 'completed' => in_array($status, ['processing', 'paid', 'shipped', 'delivered']), 'timestamp' => in_array($status, ['processing', 'paid', 'shipped', 'delivered']) ? $updatedAt?->format('g:i A') : null],
            ['key' => 'completed', 'label' => 'Completed', 'description' => 'Your order is ready!', 'completed' => in_array($status, ['shipped', 'delivered']), 'timestamp' => in_array($status, ['shipped', 'delivered']) ? $updatedAt?->format('g:i A') : null],
            ['key' => 'delivered', 'label' => 'Delivered', 'description' => 'Delivered', 'completed' => $status === 'delivered', 'timestamp' => $status === 'delivered' ? $updatedAt?->format('g:i A') : null],
        ];

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
        $map = [
            'pending' => 'Pending',
            'processing' => 'In Progress',
            'paid' => 'Confirmed',
            'shipped' => 'In Progress',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        return $map[$status ?? ''] ?? ucfirst($status ?? 'Pending');
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
