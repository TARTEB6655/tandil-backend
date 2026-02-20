<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Legacy checkout. Payment is now via Checkout.com only.
     * Use POST /api/shop/create-payment-session with order data instead.
     */
    public function checkout(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Use POST /api/shop/create-payment-session for payment. PayPal and COD are removed.',
        ], 400);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Order::where('user_id', $user->id)
            ->with(['items.product', 'user'])
            ->latest();
        
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
            ]
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with(['items.product.category', 'user', 'transactions'])->find($id);
        
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order
        ], 200);
    }

    public function markPaid(Request $request, $id)
    {
        $order = Order::with('user')->find($id);
        if (! $order) return response()->json(['success'=>false,'message'=>'Not found'],404);
        
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
            \Log::error('Failed to send payment confirmation notification: ' . $e->getMessage());
        }

        return response()->json(['status'=>true,'data'=>$order],200);
    }

    /**
     * Update order
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);
        
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'order_status' => 'nullable|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded',
            'shipping_address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $order->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->load(['items.product', 'user'])
        ], 200);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);
        
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Only allow cancellation if order is not already delivered or cancelled
        if (in_array($order->order_status, ['delivered', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel order with status: ' . $order->order_status
            ], 400);
        }

        $order->update([
            'order_status' => 'cancelled',
            'payment_status' => $order->payment_status === 'paid' ? 'refunded' : 'pending',
        ]);

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
            \Log::error('Failed to send order cancellation notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => $order->load(['items.product', 'user'])
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
                'order_number' => 'order_' . str_pad((string) $order->id, 3, '0', STR_PAD_LEFT),
                'order' => $orderArray,
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
        $data['order_number'] = 'order_' . str_pad((string) $order->id, 3, '0', STR_PAD_LEFT);
        return response()->json(['success' => true, 'message' => 'Order retrieved.', 'data' => $data], 200);
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
                'order_number' => 'order_' . str_pad((string) $order->id, 3, '0', STR_PAD_LEFT),
                'order' => $orderArray,
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
            ],
        ], 200);
    }

    /**
     * Resolve guest order by order_number (e.g. order_001) and guest_email.
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
     * Rate order
     */
    public function rate(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);
        
        if (!$order) {
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
        if (!empty($validated['review'])) {
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
            $updateData['notes'] = $notes . ($notes ? "\n\n" : '') . $ratingNote;
        }

        if (!empty($updateData)) {
            $order->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order rated successfully',
            'data' => [
                'order' => $order->load(['items.product', 'user']),
                'rating' => $validated['rating'],
                'review' => $validated['review'] ?? null,
            ]
        ], 200);
    }
}
