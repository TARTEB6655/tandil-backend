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
            'status' => true,
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
            return response()->json(['status' => false, 'message' => 'Order not found'], 404);
        }
        
        // Check if user owns the order or is admin
        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order
        ], 200);
    }

    public function markPaid(Request $request, $id)
    {
        $order = Order::find($id);
        if (! $order) return response()->json(['status'=>false,'message'=>'Not found'],404);
        $order->payment_status = 'paid';
        $order->order_status = 'paid';
        $order->paid_at = now();
        $order->save();
        return response()->json(['status'=>true,'data'=>$order],200);
    }
}
