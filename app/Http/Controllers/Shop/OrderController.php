<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
            'status' => 'pending',
        ]);

        $res = $this->paypal->createOrder(
            $order->total_amount,
            $request->input('currency','USD'),
            $request->input('return_url', url('/')),
            $request->input('cancel_url', url('/'))
        );

        return response()->json(['status'=>true,'data'=>['order'=>$order,'payment'=>$res]],200);
    }

    public function markPaid(Request $request, $id)
    {
        $order = Order::find($id);
        if (! $order) return response()->json(['status'=>false,'message'=>'Not found'],404);
        $order->status = 'paid';
        $order->paid_at = now();
        $order->save();
        return response()->json(['status'=>true,'data'=>$order],200);
    }
}
