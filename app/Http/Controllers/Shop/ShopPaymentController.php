<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Services\PayPalService;
use App\Services\ShopCheckoutOrderService;
use App\Services\StripeCheckoutSessionService;
use App\Support\StripeCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Shop checkout: Stripe Checkout or PayPal only (no Checkout.com).
 */
class ShopPaymentController extends Controller
{
    public function __construct(
        protected ShopCheckoutOrderService $orders,
        protected PayPalService $paypal,
        protected StripeCheckoutSessionService $stripeCheckout
    ) {}

    /**
     * POST /api/shop/checkout/start
     * Body: payment_method = stripe|paypal, same address/items rules as legacy create-payment-session,
     * success_url, cancel_url (PayPal cancel; Stripe cancel_url).
     */
    public function startCheckout(Request $request)
    {
        $this->orders->normalizeCheckoutRequest($request);
        if (is_string($request->input('items'))) {
            $request->merge(['items' => json_decode($request->input('items'), true) ?: []]);
        }
        if (! $request->filled('cancel_url') && $request->filled('failure_url')) {
            $request->merge(['cancel_url' => $request->input('failure_url')]);
        }

        $request->validate([
            'payment_method' => 'required|in:stripe,paypal',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
        ]);

        $method = $request->input('payment_method');
        if ($method === 'stripe' && ! StripeCredentials::isStripeUsableForCheckout()) {
            return response()->json(['success' => false, 'message' => 'Stripe is not enabled or not configured.'], 422);
        }
        if ($method === 'paypal' && ! $this->gatewayEnabled('paypal')) {
            return response()->json(['success' => false, 'message' => 'PayPal is not enabled.'], 422);
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
            ], ['items.*.product_id.exists' => 'One or more product_id values are invalid.']);
            $order = $this->orders->createGuestOrder($request, $method);
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
            ], ['items.*.product_id.exists' => 'One or more product_id values are invalid.']);
            $order = $this->orders->createLoggedInOrder($request, $method);
        }

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Could not create order.'], 422);
        }

        $currency = config('shop.currency', CartController::CURRENCY);
        $amount = (float) $order->total_amount;

        if ($method === 'stripe') {
            $stripe = $this->stripeCheckout->createForOrder(
                $order,
                $request->input('success_url'),
                $request->input('cancel_url'),
                $currency
            );
            if (isset($stripe['error'])) {
                Log::warning('Stripe checkout session failed', ['error' => $stripe['error']]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment session could not be created. Please try again.',
                ], 502);
            }
            $order->payment_reference = $stripe['session_id'] ?? null;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Stripe checkout created.',
                'data' => [
                    'gateway' => 'stripe',
                    'checkout_url' => $stripe['url'],
                    'session_id' => $stripe['session_id'],
                    'publishable_key' => StripeCredentials::publishableKey(),
                    'order_id' => $order->id,
                    'order_number' => $this->orderNumber($order),
                    'amount' => $amount,
                    'currency' => $currency,
                ],
            ], 201);
        }

        $paypalResult = $this->paypal->createOrder(
            $amount,
            $currency,
            $request->input('success_url'),
            $request->input('cancel_url'),
            (string) $order->id
        );

        if (isset($paypalResult['error'])) {
            Log::warning('PayPal create order failed', ['result' => $paypalResult]);

            return response()->json([
                'success' => false,
                'message' => 'PayPal session could not be created. Please try again.',
            ], 502);
        }

        $order->payment_reference = $paypalResult['id'] ?? null;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'PayPal checkout created.',
            'data' => [
                'gateway' => 'paypal',
                'approval_url' => $paypalResult['approval_url'],
                'paypal_order_id' => $paypalResult['id'],
                'order_id' => $order->id,
                'order_number' => $this->orderNumber($order),
                'amount' => $amount,
                'currency' => $currency,
            ],
        ], 201);
    }

    /**
     * POST /api/shop/webhooks/stripe
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = StripeCredentials::webhookSecret();
        if ($secret === '' || ! $this->verifyStripeSignature($payload, $sigHeader, $secret)) {
            Log::warning('Stripe webhook rejected (missing secret or bad signature)');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        if (($event['type'] ?? '') === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $orderId = $session['client_reference_id'] ?? ($session['metadata']['order_id'] ?? null);
            if ($orderId) {
                $order = Order::find((int) $orderId);
                if ($order && $order->payment_status !== 'paid' && $order->payment_method === 'stripe') {
                    $order->payment_status = 'paid';
                    $order->paid_at = now();
                    $order->payment_reference = $session['id'] ?? $order->payment_reference;
                    $order->save();
                    $this->notifyAdminsNewOrder($order, (float) $order->total_amount, 'Stripe');
                }
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * POST /api/shop/paypal/capture
     * Body: paypal_order_id (required), order_id (recommended for verification).
     */
    public function capturePayPal(Request $request)
    {
        $request->validate([
            'paypal_order_id' => 'required|string',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        $paypalOrderId = $request->input('paypal_order_id');
        $orderId = $request->input('order_id');

        $order = null;
        if ($orderId) {
            $order = Order::find((int) $orderId);
            if ($order && (string) $order->payment_reference !== (string) $paypalOrderId) {
                return response()->json(['success' => false, 'message' => 'Order does not match PayPal order.'], 422);
            }
        }
        if (! $order) {
            $order = Order::where('payment_reference', $paypalOrderId)->where('payment_method', 'paypal')->first();
        }
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found for this PayPal transaction.'], 404);
        }

        $result = $this->paypal->captureOrder($paypalOrderId);
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => 'PayPal capture failed.',
                'details' => $result,
            ], 502);
        }

        $status = $result['status'] ?? '';
        if ($status === 'COMPLETED' || ! empty($result['placeholder'])) {
            if ($order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                $order->paid_at = now();
                $order->save();
                $this->notifyAdminsNewOrder($order, (float) $order->total_amount, 'PayPal');
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment captured.',
                'data' => ['order_id' => $order->id, 'paypal' => $result],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unexpected PayPal status.',
            'data' => $result,
        ], 422);
    }

    protected function gatewayEnabled(string $gateway): bool
    {
        $key = "{$gateway}_enabled";
        $v = Setting::get($key, false);

        return filter_var($v, FILTER_VALIDATE_BOOLEAN)
            || $v === '1'
            || $v === 1
            || $v === true;
    }

    protected function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
    {
        if ($sigHeader === '') {
            return false;
        }
        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            }
            if ($kv[0] === 'v1') {
                $signatures[] = $kv[1];
            }
        }
        if ($timestamp === null || $signatures === []) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    protected function orderNumber(Order $order): string
    {
        return 'order_'.str_pad((string) $order->id, 3, '0', STR_PAD_LEFT);
    }

    protected function notifyAdminsNewOrder(Order $order, float $total, string $placedBy): void
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
            Log::error('Failed to send order notification: '.$e->getMessage());
        }
    }
}
