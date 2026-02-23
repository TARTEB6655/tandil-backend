<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CartController as ShopCartController;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\PayPalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    protected $paypal;

    public function __construct(PayPalService $paypal)
    {
        $this->middleware(['auth', 'role:client']);
        $this->paypal = $paypal;
    }

    public function index()
    {
        $user = Auth::user();
        $cartItems = Cart::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('client.cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = round($cartItems->sum(function ($item) {
            return $item->product ? $item->quantity * (float) $item->product->price : 0;
        }), 2);

        // Use same order summary as API (admin settings: tax %, shipping)
        $orderSummary = ShopCartController::buildOrderSummary($subtotal, 0);
        $tax = $orderSummary['tax'];
        $shipping = $orderSummary['shipping'];
        $total = $orderSummary['total'];
        $taxPercent = $orderSummary['tax_percent'];
        $shippingLabel = $orderSummary['shipping_label'];

        return view('client.checkout.index', compact('cartItems', 'subtotal', 'tax', 'shipping', 'total', 'taxPercent', 'shippingLabel', 'user'));
    }

    public function process(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:paypal,stripe,cash_on_delivery',
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_country' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
        ]);

        $user = Auth::user();
        $cartItems = Cart::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Your cart is empty.');
        }

        // Calculate totals – same as API (admin settings: tax %, shipping). Tax-exclusive: subtotal + tax + shipping = total.
        $subtotal = round($cartItems->sum(function ($item) {
            return $item->quantity * (float) $item->product->price;
        }), 2);
        $orderSummary = ShopCartController::buildOrderSummary($subtotal, 0);
        $tax = $orderSummary['tax'];
        $shipping = $orderSummary['shipping'];
        $total = $orderSummary['total'];
        $taxPercent = $orderSummary['tax_percent'];

        // Check stock availability
        foreach ($cartItems as $cartItem) {
            if ($cartItem->product->stock < $cartItem->quantity) {
                return back()->with('error', "Insufficient stock for {$cartItem->product->name}.");
            }
        }

        DB::beginTransaction();
        try {
            // Create order (save subtotal, tax, shipping for reporting)
            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $total,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'tax_percent' => $taxPercent,
                'shipping_amount' => $shipping,
                'payment_status' => $request->payment_method === 'cash_on_delivery' ? 'pending' : 'pending',
                'payment_method' => $request->payment_method,
                'order_status' => 'pending',
            ]);

            // Create order items and update stock
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->product->price,
                    'subtotal' => $cartItem->quantity * $cartItem->product->price,
                ]);

                // Update product stock
                $cartItem->product->decrement('stock', $cartItem->quantity);
            }

            // Handle payment based on method
            if ($request->payment_method === 'paypal') {
                $paypalResponse = $this->paypal->createOrder(
                    $total,
                    'USD',
                    route('client.checkout.success', ['order_id' => $order->id]),
                    route('client.checkout.cancel', ['order_id' => $order->id])
                );

                if ($paypalResponse && isset($paypalResponse['id'])) {
                    $order->update(['payment_reference' => $paypalResponse['id']]);
                    DB::commit();
                    return redirect($paypalResponse['links'][1]['href']); // Approval URL
                } else {
                    throw new \Exception('PayPal order creation failed');
                }
            } elseif ($request->payment_method === 'cash_on_delivery') {
                DB::commit();
                Cart::where('user_id', $user->id)->delete();
                return redirect()->route('client.orders.index')->with('success', 'Order placed successfully! Payment will be collected on delivery.');
            } else {
                // Stripe or other payment methods can be added here
                DB::rollBack();
                return back()->with('error', 'Payment method not yet implemented.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Order processing failed: ' . $e->getMessage());
        }
    }

    public function success(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if ($order->user_id !== $user->id) {
            abort(403);
        }

        // Verify PayPal payment
        if ($request->has('token') && $request->has('PayerID')) {
            // Complete PayPal payment
            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            Cart::where('user_id', $user->id)->delete();

            return redirect()->route('client.orders.index')->with('success', 'Payment successful! Your order has been placed.');
        }

        return redirect()->route('client.orders.index');
    }

    public function cancel($orderId)
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if ($order->user_id !== $user->id) {
            abort(403);
        }

        // Restore stock
        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        $order->delete();

        return redirect()->route('client.cart.index')->with('error', 'Payment was cancelled. Your order has been cancelled.');
    }
}

