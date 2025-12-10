<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
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

        return ApiResponse::success('Order created successfully.', [
            'order' => $order,
            'payment' => $res
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Order::where('user_id', $user->id)
            ->with('items.product')
            ->latest();
        
        $orders = $query->get();
        
        return ApiResponse::success('Orders retrieved successfully.', $orders);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items.product')->find($id);
        
        if (!$order) {
            return ApiResponse::error('Order not found', 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return ApiResponse::error('Forbidden', 403);
        }
        
        return ApiResponse::success('Order retrieved successfully.', $order);
    }

    /**
     * Update order
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);
        
        if (!$order) {
            return ApiResponse::error('Order not found', 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return ApiResponse::error('Forbidden', 403);
        }
        
        $validated = $request->validate([
            'order_status' => 'sometimes|in:pending,processing,completed,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,failed',
        ]);
        
        $order->fill($validated);
        $order->save();
        
        return ApiResponse::success('Order updated successfully.', $order->load('items.product'));
    }

    public function markPaid(Request $request, $id)
    {
        $order = Order::find($id);
        if (! $order) {
            return ApiResponse::error('Order not found', 404);
        }
        
        $order->payment_status = 'paid';
        $order->order_status = 'paid';
        $order->paid_at = now();
        $order->save();
        
        return ApiResponse::success('Order marked as paid.', $order);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);
        
        if (!$order) {
            return ApiResponse::error('Order not found', 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return ApiResponse::error('Forbidden', 403);
        }
        
        // Check if order can be cancelled
        if (in_array($order->order_status, ['completed', 'cancelled'])) {
            return ApiResponse::error('Order cannot be cancelled', 400);
        }
        
        $order->order_status = 'cancelled';
        $order->save();
        
        return ApiResponse::success('Order cancelled successfully.', $order->load('items.product'));
    }

    /**
     * Track order
     */
    public function track(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items.product')->find($id);
        
        if (!$order) {
            return ApiResponse::error('Order not found', 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return ApiResponse::error('Forbidden', 403);
        }
        
        return ApiResponse::success('Order tracking retrieved successfully.', [
            'order_id' => $order->id,
            'status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'order' => $order,
            'tracking_history' => [
                [
                    'status' => $order->order_status,
                    'date' => $order->created_at->toDateTimeString(),
                    'message' => 'Order created'
                ]
            ]
        ]);
    }

    /**
     * Rate order
     */
    public function rate(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::find($id);
        
        if (!$order) {
            return ApiResponse::error('Order not found', 404);
        }
        
        // Check if user owns the order
        if ($order->user_id !== $user->id) {
            return ApiResponse::error('Forbidden', 403);
        }
        
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);
        
        // TODO: Implement rating system if you have a ratings table
        // For now, just return success
        return ApiResponse::success('Order rated successfully.', [
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null
        ]);
    }
}
