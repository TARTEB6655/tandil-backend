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
use App\Services\PayPalService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected PayPalService $paypal;

    public function __construct(PayPalService $paypal)
    {
        $this->paypal = $paypal;
    }

    /**
     * Create order (Place Order from Review step).
     * Accepts: address_id (required), payment_method (required: card|paypal|cod or id), optional items/total_amount.
     * If items not provided, order is built from current cart; cart is cleared after order creation.
     * Order stores: shipping_address_id, shipping_amount, payment_method, total_amount.
     */
    public function checkout(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'address_id' => 'required|exists:user_addresses,id',
            'payment_method' => 'required|string|max:50',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'total_amount' => 'nullable|numeric|min:0',
        ]);

        $addressId = (int) $request->input('address_id');
        $address = UserAddress::where('user_id', $user->id)->findOrFail($addressId);

        $paymentMethod = $request->input('payment_method');
        $paymentType = in_array($paymentMethod, ['paypal', 'cod']) ? $paymentMethod : 'card';

        $packageId = $request->input('package_id');
        $items = $request->input('items', []);

        $shippingAmount = (float) config('shop.shipping_amount', 9.99);

        if (empty($items) && ! $packageId) {
            $cartItems = Cart::where('user_id', $user->id)->with('product')->get();
            $validCart = $cartItems->filter(fn ($c) => $c->product !== null);
            if ($validCart->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Cart is empty. Add items or send items in request.'], 422);
            }
            $subtotal = $validCart->sum(fn ($c) => $c->quantity * (float) $c->product->price);
            $total = round($subtotal + $shippingAmount, 2);
            foreach ($validCart as $c) {
                $items[] = ['product_id' => $c->product_id, 'qty' => $c->quantity];
            }
        } else {
            $total = (float) $request->input('total_amount', 0);
            if ($total <= 0 && ! empty($items)) {
                $subtotal = 0;
                foreach ($items as $item) {
                    $p = Product::find($item['product_id'] ?? null);
                    if ($p) {
                        $subtotal += (float) $p->price * (int) ($item['qty'] ?? 1);
                    }
                }
                $total = round($subtotal + $shippingAmount, 2);
            }
        }

        $orderData = [
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'total_amount' => $total,
            'shipping_amount' => $shippingAmount,
            'order_status' => 'processing',
            'payment_status' => 'pending',
            'payment_method' => $paymentType,
        ];

        if ($packageId) {
            $package = Package::where('id', $packageId)->where('is_active', true)->first();
            if ($package) {
                $orderData['package_id'] = $package->id;
                $orderData['total_amount'] = (float) $package->price;
                $orderData['shipping_amount'] = $shippingAmount;
                $total = $orderData['total_amount'];
            }
        }

        $order = Order::create($orderData);

        if (! isset($orderData['package_id']) && ! empty($items)) {
            foreach ($items as $item) {
                $product = Product::find($item['product_id'] ?? null);
                if ($product) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $item['qty'] ?? 1,
                        'price' => $product->price,
                        'subtotal' => $product->price * ($item['qty'] ?? 1),
                    ]);
                }
            }
        }

        if (empty($request->input('items')) && ! $packageId) {
            Cart::where('user_id', $user->id)->delete();
        }

        try {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'New Order Received',
                    "A new order #{$order->id} has been placed by {$user->name} for AED {$total}."
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send order notification: ' . $e->getMessage());
        }

        if ($paymentType === 'paypal') {
            $res = $this->paypal->createOrder(
                (float) $order->total_amount,
                $request->input('currency', 'AED'),
                $request->input('return_url', url('/')),
                $request->input('cancel_url', url('/'))
            );
            return response()->json(['success' => true, 'message' => 'Order created. Complete payment with PayPal.', 'data' => ['order' => $order->load('shippingAddress'), 'payment' => $res]], 201);
        }

        if ($paymentType === 'cod') {
            return response()->json(['success' => true, 'message' => 'Order placed. Cash on Delivery.', 'data' => ['order' => $order->load('shippingAddress')]], 201);
        }

        return response()->json(['success' => true, 'message' => 'Order created. Complete card payment on your gateway.', 'data' => ['order' => $order->load('shippingAddress')]], 201);
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
     * Track order
     */
    public function track(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with(['items.product', 'user'])->find($id);
        
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order tracking information retrieved successfully',
            'data' => [
                'order' => $order,
                'tracking' => [
                    'status' => $order->order_status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'paid_at' => $order->paid_at,
                ]
            ]
        ], 200);
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
