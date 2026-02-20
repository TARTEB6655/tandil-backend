<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserAddress;
use App\Notifications\AdminNotification;
use App\Services\CheckoutComService;
use Illuminate\Http\Request;

/**
 * Checkout.com: create payment session (backend only), webhook for payment result.
 * Frontend never creates payment directly; backend creates session and verifies via webhook.
 */
class CheckoutComController extends Controller
{
    public function __construct(protected CheckoutComService $checkout)
    {
    }

    /**
     * POST /api/shop/create-payment-session
     * Receive order data → create order (pending) → call Checkout.com → return session_id for frontend.
     * Logged-in: address_id OR inline address, items or cart.
     * Guest: email, full_name, phone_number, street_address, city, country, items (required).
     */
    public function createPaymentSession(Request $request)
    {
        $this->normalizeCheckoutRequest($request);
        if (is_string($request->input('items'))) {
            $request->merge(['items' => json_decode($request->input('items'), true) ?: []]);
        }

        $user = $request->user();
        $isGuest = $user === null;

        if ($isGuest) {
            $request->validate([
                'email' => 'required|email',
                'full_name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20',
                'street_address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'country' => 'required|string|max:100',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.qty' => 'required|integer|min:1',
                'success_url' => 'required|url',
                'failure_url' => 'required|url',
            ], ['items.*.product_id.exists' => 'One or more product_id values are invalid.']);
            $order = $this->createGuestOrder($request);
        } else {
            $request->validate([
                'address_id' => 'nullable|exists:user_addresses,id',
                'full_name' => 'required_without:address_id|string|max:255',
                'phone_number' => 'required_without:address_id|string|max:20',
                'street_address' => 'required_without:address_id|string|max:500',
                'city' => 'required_without:address_id|string|max:100',
                'country' => 'required_without:address_id|string|max:100',
                'items' => 'nullable|array',
                'items.*.product_id' => 'required_with:items|exists:products,id',
                'items.*.qty' => 'required_with:items|integer|min:1',
                'success_url' => 'required|url',
                'failure_url' => 'required|url',
            ], ['items.*.product_id.exists' => 'One or more product_id values are invalid.']);
            $order = $this->createLoggedInOrder($request);
        }

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Could not create order.'], 422);
        }

        $currency = config('shop.currency', 'AED');
        $amount = (float) $order->total_amount;
        $reference = (string) $order->id;
        $successUrl = $request->input('success_url');
        $failureUrl = $request->input('failure_url');

        $result = $this->checkout->createPaymentSession(
            $reference,
            $amount,
            $currency,
            $successUrl,
            $failureUrl,
            ['order_number' => 'order_' . str_pad((string) $order->id, 3, '0', STR_PAD_LEFT)]
        );

        if (isset($result['error'])) {
            \Log::warning('Checkout.com createPaymentSession failed', ['result' => $result]);
            return response()->json([
                'success' => false,
                'message' => 'Payment session could not be created. Please try again.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment session created.',
            'data' => [
                'session_id' => $result['session_id'],
                'order_id' => $order->id,
                'order_number' => 'order_' . str_pad((string) $order->id, 3, '0', STR_PAD_LEFT),
                'public_key' => $result['public_key'] ?? $this->checkout->getPublicKey(),
                'amount' => $amount,
                'currency' => $currency,
            ],
        ], 201);
    }

    /**
     * POST /api/shop/webhooks/checkout-com
     * Checkout.com sends payment result here. Verify signature, update order, store transaction.
     */
    public function webhook(Request $request)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('Cko-Signature', $request->header('cko-signature', ''));

        if (! $this->checkout->verifyWebhookSignature($rawBody, $signature)) {
            \Log::warning('Checkout.com webhook signature invalid');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! $payload) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $type = $payload['type'] ?? $payload['event_type'] ?? '';
        $data = $payload['data'] ?? $payload;

        if (in_array($type, ['payment_captured', 'payment_approved', 'payment_paid'], true)) {
            $paymentId = $data['id'] ?? $data['payment_id'] ?? null;
            $reference = $data['reference'] ?? $data['metadata']['order_id'] ?? null;
            if ($reference) {
                $order = Order::find($reference);
                if ($order && $order->payment_status !== 'paid') {
                    $order->payment_status = 'paid';
                    $order->paid_at = now();
                    $order->payment_method = 'checkout_com';
                    $order->payment_reference = $paymentId;
                    $order->save();
                    $this->notifyAdminsNewOrder($order, (float) $order->total_amount, 'Checkout.com');
                }
            }
        }

        return response()->json(['received' => true], 200);
    }

    private function createGuestOrder(Request $request): ?Order
    {
        $items = $request->input('items');
        $subtotal = 0;
        foreach ($items as $item) {
            $p = \App\Models\Product::find($item['product_id'] ?? null);
            if ($p) {
                $subtotal += (float) $p->price * (int) ($item['qty'] ?? 1);
            }
        }
        $subtotal = round($subtotal, 2);
        $summary = CartController::buildOrderSummary($subtotal);
        $total = $summary['total'];
        $shippingAmount = $summary['shipping'];

        $order = Order::create([
            'user_id' => null,
            'guest_email' => $request->input('email'),
            'guest_full_name' => $request->input('full_name'),
            'guest_phone' => $request->input('phone_number'),
            'guest_street_address' => $request->input('street_address'),
            'guest_city' => $request->input('city'),
            'guest_state' => $request->input('state'),
            'guest_zip_code' => $request->input('zip_code'),
            'guest_country' => $request->input('country'),
            'shipping_address_id' => null,
            'total_amount' => $total,
            'shipping_amount' => $shippingAmount,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'checkout_com',
        ]);

        foreach ($items as $item) {
            $product = \App\Models\Product::find($item['product_id'] ?? null);
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

        return $order;
    }

    private function createLoggedInOrder(Request $request): ?Order
    {
        $user = $request->user();
        $addressId = $request->input('address_id');
        $items = $request->input('items', []);

        if ($addressId) {
            $address = UserAddress::where('user_id', $user->id)->find((int) $addressId);
            if (! $address) {
                return null;
            }
        } else {
            $address = new UserAddress;
            $address->user_id = $user->id;
            $address->full_name = $request->input('full_name');
            $address->phone_number = $request->input('phone_number');
            $address->street_address = $request->input('street_address');
            $address->city = $request->input('city');
            $address->state = $request->input('state');
            $address->zip_code = $request->input('zip_code');
            $address->country = $request->input('country');
            $address->is_default = false;
            $address->save();
        }

        if (empty($items)) {
            $cartItems = Cart::where('user_id', $user->id)->with('product')->get();
            $validCart = $cartItems->filter(fn ($c) => $c->product !== null);
            if ($validCart->isEmpty()) {
                return null;
            }
            $subtotal = round($validCart->sum(fn ($c) => $c->quantity * (float) $c->product->price), 2);
            foreach ($validCart as $c) {
                $items[] = ['product_id' => $c->product_id, 'qty' => $c->quantity];
            }
        } else {
            $subtotal = 0;
            foreach ($items as $item) {
                $p = \App\Models\Product::find($item['product_id'] ?? null);
                if ($p) {
                    $subtotal += (float) $p->price * (int) ($item['qty'] ?? 1);
                }
            }
            $subtotal = round($subtotal, 2);
        }

        $summary = CartController::buildOrderSummary($subtotal);
        $total = $summary['total'];
        $shippingAmount = $summary['shipping'];

        $order = Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'total_amount' => $total,
            'shipping_amount' => $shippingAmount,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'checkout_com',
        ]);

        foreach ($items as $item) {
            $product = \App\Models\Product::find($item['product_id'] ?? null);
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

        if (empty($request->input('items'))) {
            Cart::where('user_id', $user->id)->delete();
        }

        return $order;
    }

    private function normalizeCheckoutRequest(Request $request): void
    {
        $all = $request->all();
        $shipping = $all['shipping_address'] ?? null;
        if (is_array($shipping)) {
            $all['full_name'] = $shipping['fullName'] ?? $shipping['full_name'] ?? $all['full_name'] ?? null;
            $all['phone_number'] = $shipping['phone'] ?? $shipping['phone_number'] ?? $all['phone_number'] ?? null;
            $all['street_address'] = $shipping['street'] ?? $shipping['street_address'] ?? $all['street_address'] ?? null;
            $all['city'] = $shipping['city'] ?? $all['city'] ?? null;
            $all['state'] = $shipping['state'] ?? $all['state'] ?? null;
            $all['zip_code'] = $shipping['zipCode'] ?? $shipping['zip_code'] ?? $all['zip_code'] ?? null;
            $all['country'] = $shipping['country'] ?? $all['country'] ?? null;
        }
        $request->merge($all);
    }

    private function notifyAdminsNewOrder(Order $order, $total, string $placedBy): void
    {
        try {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'New Order Received',
                    "A new order #{$order->id} has been placed by {$placedBy} for AED {$total}."
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send order notification: ' . $e->getMessage());
        }
    }
}
