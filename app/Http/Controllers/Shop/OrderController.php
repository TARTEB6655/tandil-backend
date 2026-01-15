<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\PayPalService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected PayPalService $paypal;

    public function __construct(PayPalService $paypal)
    {
        $this->paypal = $paypal;
    }

    // Create order (simple) and return payment approval url
    public function checkout(Request $request)
    {
        $user = $request->user();
        $items = $request->input('items', []); // array of {product_id, qty}

        // Minimal order creation; compute total on server side in production
        $total = (float) $request->input('total_amount', 0);

        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => $total,
            'order_status' => 'processing',
            'payment_status' => 'pending',
        ]);

        // Create order items
        foreach ($items as $item) {
            $product = Product::find($item['product_id'] ?? null);
            if ($product) {
                \App\Models\OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['qty'] ?? 1,
                    'price' => $product->price,
                    'subtotal' => $product->price * ($item['qty'] ?? 1),
                ]);
            }
        }

        $res = $this->paypal->createOrder(
            $order->total_amount,
            $request->input('currency','USD'),
            $request->input('return_url', url('/')),
            $request->input('cancel_url', url('/'))
        );

        return response()->json(['status'=>true,'data'=>['order'=>$order,'payment'=>$res]],200);
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
        $order = Order::find($id);
        if (! $order) return response()->json(['success'=>false,'message'=>'Not found'],404);
        $order->payment_status = 'paid';
        $order->order_status = 'paid';
        $order->paid_at = now();
        $order->save();
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
