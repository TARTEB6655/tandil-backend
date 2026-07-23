<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPaymentMethod;
use App\Notifications\AdminNotification;
use App\Services\PayPalService;
use App\Services\ShopCheckoutOrderService;
use App\Services\ShopStripeMobilePaymentService;
use App\Services\ShopWalletRedemptionService;
use App\Services\StripeCheckoutSessionService;
use App\Support\OrderToVisitDispatcher;
use App\Support\RefundPolicy;
use App\Support\OrderSupervisorNotifier;
use App\Support\StripeCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
            // Mobile often sends false before the user ticks; omit the field or send true — do not use strict "accepted" rule.
            'accepted_refund_policy' => 'nullable|boolean',
            'paypal_payment_method_id' => 'nullable|integer',
            'use_wallet' => 'nullable|boolean',
            'wallet_amount' => 'nullable|numeric|min:0',
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
                'special_instructions' => 'nullable|string|max:2000',
            ], ['items.*.product_id.exists' => 'One or more product_id values are invalid.']);
            if ($blocked = $this->blockedByInactiveOperationalArea($request, null)) {
                return $blocked;
            }
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
                'special_instructions' => 'nullable|string|max:2000',
            ], ['items.*.product_id.exists' => 'One or more product_id values are invalid.']);
            if ($blocked = $this->blockedByInactiveOperationalArea($request, $user)) {
                return $blocked;
            }
            $order = $this->orders->createLoggedInOrder($request, $method);
        }

        if (! $order) {
            if ($isGuest) {
                $msg = 'Could not create order. Check product_id values and quantities in items.';
            } else {
                $hasItems = is_array($request->input('items')) && count($request->input('items')) > 0;
                $cartCount = \App\Models\Cart::where('user_id', $user->id)->count();
                if (! $hasItems && $cartCount === 0) {
                    $msg = 'Your cart is empty. Add products to the cart or include an items array (product_id + qty) in this request.';
                } elseif ($request->filled('address_id')) {
                    $msg = 'Could not create order. address_id must belong to your account, or omit it and send full shipping fields (full_name, phone_number, street_address, city, country).';
                } else {
                    $msg = 'Could not create order. Check address fields, items, or cart contents.';
                }
            }

            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        $currency = config('shop.currency', CartController::CURRENCY);
        $walletApplied = 0.0;
        $amountToCharge = (float) $order->total_amount;

        if (! $isGuest && Schema::hasColumn('orders', 'wallet_amount_applied')) {
            $user->refresh();
            $useWallet = $request->boolean('use_wallet');
            $hasWalletAmount = $request->has('wallet_amount');
            $requested = max(0, (float) $request->input('wallet_amount', 0));
            if ($useWallet && $requested <= 0) {
                // Keep preview/start consistent: use_wallet=true with 0 or missing amount means "apply max possible".
                $requested = (float) ($user->wallet_balance ?? 0);
            }

            if ($requested > 0) {
                $bal = (float) ($user->wallet_balance ?? 0);
                $walletApplied = round(min($requested, $bal, $amountToCharge), 2);
                $order->wallet_amount_applied = $walletApplied;
                $order->save();
            } elseif ($hasWalletAmount || $useWallet) {
                // Explicit wallet toggle/value should clear any prior applied amount on this order.
                $order->wallet_amount_applied = 0;
                $order->save();
            }
            $amountToCharge = max(0, round((float) $order->total_amount - $walletApplied, 2));
        }

        $minAedCard = 2.0;
        if (strtolower($currency) === 'aed' && $walletApplied > 0 && $amountToCharge > 0 && $amountToCharge < $minAedCard) {
            $shortfall = round($minAedCard - $amountToCharge, 2);
            $walletApplied = max(0, round($walletApplied - $shortfall, 2));
            $amountToCharge = max(0, round((float) $order->total_amount - $walletApplied, 2));
            $order->wallet_amount_applied = $walletApplied;
            $order->save();
        }

        if (! $isGuest && $amountToCharge <= 0.0001 && $walletApplied > 0.0001) {
            try {
                DB::transaction(function () use ($order, $user) {
                    $o = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                    if ($o->payment_status === 'paid') {
                        return;
                    }
                    app(ShopWalletRedemptionService::class)->redeem($user, (float) $o->wallet_amount_applied, (int) $o->id, $o->publicOrderNumber());
                    $o->payment_status = 'paid';
                    $o->paid_at = now();
                    $o->payment_method = 'wallet';
                    $o->payment_reference = 'wallet:'.$o->id.':'.uniqid('', true);
                    $o->wallet_redeemed_at = now();
                    $o->save();
                });
            } catch (\Throwable $e) {
                Log::warning('Wallet-only checkout failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() === 'Insufficient wallet balance.'
                        ? 'Insufficient wallet balance.'
                        : 'Wallet checkout could not be completed. Please try again.',
                ], 422);
            }
            $order->refresh();
            $this->notifyAdminsNewOrder($order, (float) $order->total_amount, 'Wallet');

            return response()->json([
                'success' => true,
                'message' => 'Order paid from wallet.',
                'data' => [
                    'gateway' => 'wallet',
                    'order_id' => $order->id,
                    'order_number' => $this->orderNumber($order),
                    'order_total' => (float) $order->total_amount,
                    'wallet_amount_applied' => $walletApplied,
                    'amount_charged' => 0.0,
                    'currency' => $currency,
                    'status' => 'paid',
                    'refund_policy' => RefundPolicy::policyForApi(),
                ],
            ], 201);
        }

        $amount = $amountToCharge;

        if ($method === 'stripe') {
            $stripe = $this->stripeCheckout->createForOrder(
                $order,
                $request->input('success_url'),
                $request->input('cancel_url'),
                $currency
            );
            if (isset($stripe['error'])) {
                Log::warning('Stripe checkout session failed', ['error' => $stripe['error']]);
                $err = (string) $stripe['error'];
                $isAmount = str_contains(strtolower($err), 'invalid amount') || str_contains(strtolower($err), 'amount');

                return response()->json([
                    'success' => false,
                    'message' => $isAmount
                        ? 'The amount to charge is too small for Stripe (minimum ~0.01 in your currency). Adjust wallet_amount or cart total.'
                        : 'Payment session could not be created. Check Stripe keys in admin settings and try again.',
                    'details' => config('app.debug') ? $err : null,
                ], $isAmount ? 422 : 502);
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
                    'order_total' => (float) $order->total_amount,
                    'wallet_amount_applied' => $walletApplied,
                    'amount' => $amount,
                    'currency' => $currency,
                    'refund_policy' => RefundPolicy::policyForApi(),
                ],
            ], 201);
        }

        $savedPayPalMethod = null;
        if ($method === 'paypal' && $user !== null && $request->filled('paypal_payment_method_id')) {
            $savedPayPalMethod = UserPaymentMethod::query()
                ->where('id', (int) $request->input('paypal_payment_method_id'))
                ->where('user_id', $user->id)
                ->where('gateway', 'paypal')
                ->first();
            if (! $savedPayPalMethod) {
                return response()->json([
                    'success' => false,
                    'message' => 'Saved PayPal payment method not found.',
                ], 404);
            }
        }

        $paypalResult = $this->paypal->createOrder(
            $amount,
            $currency,
            $request->input('success_url'),
            $request->input('cancel_url'),
            (string) $order->id,
            [
                'payment_token_id' => $savedPayPalMethod?->provider_method_id,
                'store_in_vault' => $user !== null && $savedPayPalMethod === null,
            ]
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

        if ($savedPayPalMethod !== null) {
            $captured = $this->paypal->captureOrder((string) ($paypalResult['id'] ?? ''));
            if (isset($captured['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'PayPal capture failed.',
                    'details' => $captured,
                ], 502);
            }

            if (($captured['status'] ?? '') === 'COMPLETED' || ! empty($captured['placeholder'])) {
                if ($order->payment_status !== 'paid') {
                    $order->payment_status = 'paid';
                    $order->paid_at = now();
                    $order->save();
                    app(ShopWalletRedemptionService::class)->redeemAfterOrderPaid($order->fresh());
                    $this->notifyAdminsNewOrder($order->fresh(), (float) $order->total_amount, 'PayPal');
                }

                return response()->json([
                    'success' => true,
                    'message' => 'PayPal payment captured using saved method.',
                    'data' => [
                        'gateway' => 'paypal',
                        'saved_payment_method_id' => $savedPayPalMethod->id,
                        'paypal_order_id' => $paypalResult['id'] ?? null,
                        'order_id' => $order->id,
                        'order_number' => $this->orderNumber($order),
                        'order_total' => (float) $order->total_amount,
                        'wallet_amount_applied' => (float) ($order->wallet_amount_applied ?? 0),
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'paid',
                        'refund_policy' => RefundPolicy::policyForApi(),
                    ],
                ], 201);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'PayPal checkout created.',
            'data' => [
                'gateway' => 'paypal',
                'approval_url' => $paypalResult['approval_url'],
                'paypal_order_id' => $paypalResult['id'],
                'vaulting_attempted' => $user !== null && $savedPayPalMethod === null,
                'order_id' => $order->id,
                'order_number' => $this->orderNumber($order),
                'order_total' => (float) $order->total_amount,
                'wallet_amount_applied' => $walletApplied,
                'amount' => $amount,
                'currency' => $currency,
                'refund_policy' => RefundPolicy::policyForApi(),
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
                    app(ShopWalletRedemptionService::class)->redeemAfterOrderPaid($order->fresh());
                    $this->notifyAdminsNewOrder($order->fresh(), (float) $order->total_amount, 'Stripe');
                }
            }
        }

        if (($event['type'] ?? '') === 'payment_intent.succeeded') {
            $pi = $event['data']['object'] ?? [];
            if (is_array($pi)) {
                $purpose = (string) ($pi['metadata']['purpose'] ?? '');
                // Wallet Add Money — never create shop orders.
                if ($purpose === \App\Services\WalletTopUpStripeService::PURPOSE) {
                    app(\App\Services\WalletTopUpStripeService::class)->fulfillFromWebhookPaymentIntent($pi);
                } else {
                    app(ShopStripeMobilePaymentService::class)->fulfillFromWebhookPaymentIntent($pi);
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
                app(ShopWalletRedemptionService::class)->redeemAfterOrderPaid($order->fresh());
                $this->notifyAdminsNewOrder($order->fresh(), (float) $order->total_amount, 'PayPal');
            }
            $this->upsertVaultedPayPalMethod($request->user(), $order, $result);

            return response()->json([
                'success' => true,
                'message' => 'Payment captured.',
                'data' => [
                    'order_id' => $order->id,
                    'paypal' => $result,
                    'saved_payment_methods' => $order->user_id
                        ? UserPaymentMethod::query()
                            ->where('user_id', $order->user_id)
                            ->where('gateway', 'paypal')
                            ->orderByDesc('is_default')
                            ->orderByDesc('id')
                            ->get(['id', 'label', 'email', 'is_default'])
                        : [],
                ],
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
        return $order->publicOrderNumber();
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

            OrderSupervisorNotifier::notifySupervisorsForPaidOrder($order, $total, $placedBy);
            OrderToVisitDispatcher::createVisitForPaidOrder($order);
        } catch (\Exception $e) {
            Log::error('Failed to send order notification: '.$e->getMessage());
        }
    }

    protected function upsertVaultedPayPalMethod(?User $actor, Order $order, array $capture): void
    {
        $userId = $order->user_id ?? $actor?->id;
        if (! $userId) {
            return;
        }
        $details = $this->paypal->extractVaultedPaymentMethod($capture);
        if (! is_array($details) || empty($details['provider_method_id'])) {
            return;
        }

        $providerId = (string) $details['provider_method_id'];
        $pm = UserPaymentMethod::query()->firstOrNew([
            'gateway' => 'paypal',
            'provider_method_id' => $providerId,
        ]);
        $pm->user_id = $userId;
        $pm->provider_customer_id = $details['provider_customer_id'] ?? null;
        $pm->email = $details['email'] ?? null;
        $pm->brand = $details['brand'] ?? 'paypal';
        $pm->last4 = $details['last4'] ?? null;
        $pm->expiry_month = $details['expiry_month'] ?? null;
        $pm->expiry_year = $details['expiry_year'] ?? null;
        $pm->label = $pm->email ? ('PayPal ' . $pm->email) : 'PayPal';
        $pm->meta = is_array($details['raw'] ?? null) ? $details['raw'] : null;

        // First PayPal method for this user becomes default.
        if (! $pm->exists) {
            $hasDefault = UserPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('gateway', 'paypal')
                ->where('is_default', true)
                ->exists();
            $pm->is_default = ! $hasDefault;
        }

        $pm->save();
    }

    /**
     * Block checkout when the resolved area exists but is inactive.
     */
    private function blockedByInactiveOperationalArea(Request $request, ?User $user): ?\Illuminate\Http\JsonResponse
    {
        if (! Schema::hasTable('areas') || ! Schema::hasColumn('areas', 'is_active')) {
            return null;
        }

        $city = null;
        $country = null;
        if ($user !== null && $request->filled('address_id')) {
            $address = UserAddress::query()
                ->where('user_id', $user->id)
                ->find((int) $request->input('address_id'));
            if ($address) {
                $city = $address->city;
                $country = $address->country;
            }
        }
        if ($city === null || $city === '') {
            $city = (string) $request->input('city', '');
            $country = (string) $request->input('country', '');
        }

        $city = trim((string) $city);
        $country = trim((string) $country);
        if ($city === '') {
            return null;
        }

        $query = Area::query();
        if ($country !== '' && Schema::hasColumn('areas', 'country')) {
            $query->whereRaw('LOWER(TRIM(country)) = ?', [mb_strtolower($country)]);
        }
        $query->where(function ($q) use ($city) {
            $cityLc = mb_strtolower($city);
            if (Schema::hasColumn('areas', 'name')) {
                $q->orWhereRaw('LOWER(TRIM(name)) = ?', [$cityLc])
                    ->orWhere('name', 'like', '%' . $city . '%');
            }
            if (Schema::hasColumn('areas', 'location')) {
                $q->orWhereRaw('LOWER(TRIM(location)) = ?', [$cityLc])
                    ->orWhere('location', 'like', '%' . $city . '%');
            }
        });

        $matches = $query->get();
        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->contains(fn ($a) => (bool) ($a->is_active ?? true))) {
            return null;
        }

        return response()->json([
            'success' => false,
            'title' => 'Oops!',
            'error_title' => 'Oops!',
            'message' => 'Currently this area is not operational. Please try sometime.',
        ], 422);
    }
}
