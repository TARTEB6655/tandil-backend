<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShopMobileCheckout;
use App\Models\User;
use App\Models\UserAddress;
use App\Notifications\AdminNotification;
use App\Support\OrderToVisitDispatcher;
use App\Support\OrderSupervisorNotifier;
use App\Support\RefundPolicy;
use App\Support\StripeCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stripe Payment Sheet flow for mobile: create PaymentIntent + confirm order after success.
 */
class ShopStripeMobilePaymentService
{
    /** Stripe minimum for AED (~USD 0.50 equivalent); amounts are in fils (1 AED = 100). */
    private const MIN_AMOUNT_MINOR_AED = 200;

    public function createPaymentIntent(Request $request, User $user): array
    {
        if (! StripeCredentials::isStripeUsableForCheckout()) {
            return $this->err('Stripe is not enabled or not configured.', 422);
        }

        $this->mergeNormalizedShipping($request);

        $request->validate([
            'is_buy_now' => 'sometimes|boolean',
            'product_id' => 'required_if:is_buy_now,true|nullable|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
            'wallet_amount' => 'nullable|numeric|min:0',
            'shipping' => 'required|array',
            'shipping.full_name' => 'required|string|max:255',
            'shipping.phone' => 'required|string|max:40',
            'shipping.street' => 'required|string|max:500',
            'shipping.line2' => 'nullable|string|max:500',
            'shipping.city' => 'required|string|max:100',
            'shipping.state' => 'nullable|string|max:100',
            'shipping.zip_code' => 'nullable|string|max:20',
            'shipping.country' => 'required|string|max:100',
            'shipping.company' => 'nullable|string|max:255',
            'shipping.email' => 'nullable|email|max:255',
            'special_instructions' => 'nullable|string|max:2000',
        ]);

        $isBuyNow = $request->boolean('is_buy_now');
        if ($isBuyNow && ! $request->filled('product_id')) {
            return $this->err('product_id is required when is_buy_now is true.', 422);
        }

        $previewRequest = Request::create($request->url(), 'GET', $isBuyNow ? [
            'product_id' => $request->input('product_id'),
            'quantity' => $request->input('quantity', $request->input('qty', 1)),
        ] : []);

        $preview = CartController::checkoutPreview($previewRequest, $user->id);
        if ($preview['items']->isEmpty()) {
            return $this->err('Your cart is empty. Add items or use buy now with a product.', 422);
        }

        $summary = CartController::buildOrderSummary($preview['subtotal'], 0);
        $total = (float) $summary['total'];
        $currency = strtolower((string) config('shop.currency', CartController::CURRENCY));

        $lines = [];
        foreach ($preview['items'] as $cart) {
            if ($cart->product === null) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $cart->product_id,
                'quantity' => (int) $cart->quantity,
                'unit_price' => (float) $cart->product->price,
            ];
        }
        if ($lines === []) {
            return $this->err('No valid line items for checkout.', 422);
        }

        $ship = $request->input('shipping');
        $shippingJson = [
            'full_name' => $ship['full_name'],
            'phone_number' => $ship['phone'],
            'street_address' => $ship['street'],
            'line2' => $ship['line2'] ?? '',
            'city' => $ship['city'],
            'state' => $ship['state'] ?? '',
            'zip_code' => $ship['zip_code'] ?? '',
            'country' => $ship['country'],
            'company' => $ship['company'] ?? '',
            'email' => $ship['email'] ?? '',
        ];

        $specialRaw = $request->input('special_instructions');
        $specialInstructions = null;
        if (is_string($specialRaw)) {
            $t = trim($specialRaw);
            $specialInstructions = $t === '' ? null : mb_substr($t, 0, 2000);
        }

        $user->refresh();
        $walletRequested = max(0, (float) $request->input('wallet_amount', 0));
        $walletApplied = 0.0;
        if ($walletRequested > 0) {
            $bal = (float) ($user->wallet_balance ?? 0);
            $walletApplied = round(min($walletRequested, $bal, $total), 2);
        }
        $cardTotal = max(0, round($total - $walletApplied, 2));
        if ($currency === 'aed' && $walletApplied > 0 && $cardTotal > 0 && $cardTotal < 2.0) {
            $shortfall = round(2.0 - $cardTotal, 2);
            $walletApplied = max(0, round($walletApplied - $shortfall, 2));
            $cardTotal = max(0, round($total - $walletApplied, 2));
        }

        if ($cardTotal <= 0.0001 && $walletApplied > 0.0001) {
            try {
                $order = DB::transaction(function () use ($user, $preview, $ship, $specialInstructions, $summary, $total, $walletApplied, $isBuyNow, $currency, $shippingJson) {
                    $linesW = [];
                    foreach ($preview['items'] as $cart) {
                        if ($cart->product === null) {
                            continue;
                        }
                        $linesW[] = [
                            'product_id' => (int) $cart->product_id,
                            'quantity' => (int) $cart->quantity,
                            'unit_price' => (float) $cart->product->price,
                        ];
                    }
                    $row = new ShopMobileCheckout([
                        'user_id' => $user->id,
                        'source' => $isBuyNow ? 'buy_now' : 'cart',
                        'currency' => $currency,
                        'amount_minor' => 0,
                        'lines_json' => $linesW,
                        'shipping_json' => $shippingJson,
                        'subtotal_amount' => $summary['subtotal'],
                        'tax_amount' => $summary['tax'],
                        'tax_percent' => $summary['tax_percent'],
                        'shipping_amount' => $summary['shipping'],
                        'total_amount' => $total,
                        'wallet_amount_applied' => $walletApplied,
                        'special_instructions' => $specialInstructions,
                    ]);

                    return $this->buildPaidOrder($user, $row, 'wallet:'.uniqid('', true));
                });
            } catch (\Throwable $e) {
                Log::warning('Mobile wallet-only checkout failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);

                return $this->err(
                    $e->getMessage() === 'Insufficient wallet balance.'
                        ? 'Insufficient wallet balance.'
                        : 'Could not complete wallet checkout.',
                    422
                );
            }
            $this->notifyAdminsNewOrder($order, (float) $order->total_amount, 'Wallet (mobile)');

            return [
                'ok' => true,
                'message' => 'Order paid from wallet.',
                'data' => array_merge($this->orderPayload($order), [
                    'gateway' => 'wallet',
                    'wallet_amount_applied' => $walletApplied,
                    'refund_policy' => RefundPolicy::policyForApi(),
                ]),
            ];
        }

        $amountMinor = (int) round($cardTotal * 100);

        if ($currency === 'aed' && $amountMinor < self::MIN_AMOUNT_MINOR_AED) {
            return $this->err('Order total is below the minimum for card payment (2.00 AED).', 422);
        }
        if ($amountMinor < 50) {
            return $this->err('Order total is too low for card payment.', 422);
        }

        $secret = StripeCredentials::secretKey();
        $this->cancelLongAbandonedMobileCheckouts($user, $secret, 15);

        $fingerprint = hash('sha256', json_encode([
            'lines' => $lines,
            'amount_minor' => $amountMinor,
            'wallet_applied' => round($walletApplied, 2),
            'ship' => $shippingJson,
            'special_instructions' => $specialInstructions,
        ], JSON_THROW_ON_ERROR));

        $reuse = $this->maybeReuseRecentPaymentIntent($user, $secret, $fingerprint, $amountMinor);
        if ($reuse !== null) {
            return $reuse;
        }

        $checkoutRef = (string) Str::ulid();

        $fullName = trim((string) $ship['full_name']);
        $phone = trim((string) $ship['phone']);
        $street = trim((string) $ship['street']);
        $line2 = trim((string) ($ship['line2'] ?? ''));
        $city = trim((string) $ship['city']);
        $state = trim((string) ($ship['state'] ?? ''));
        $zip = trim((string) ($ship['zip_code'] ?? ''));
        $countryRaw = trim((string) ($ship['country'] ?? ''));
        $company = trim((string) ($ship['company'] ?? ''));
        $shipEmail = trim((string) ($ship['email'] ?? ''));
        $countryCode = self::stripeCountryCode($countryRaw);

        $cartPreview = $this->cartPreviewLabel($preview['items']);
        $accountName = trim((string) ($user->name ?? ''));
        $accountPhone = trim((string) ($user->phone ?? ''));
        $userEmail = trim((string) ($user->email ?? ''));

        $receiptEmail = '';
        if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $receiptEmail = $userEmail;
        } elseif ($shipEmail !== '' && filter_var($shipEmail, FILTER_VALIDATE_EMAIL)) {
            $receiptEmail = $shipEmail;
        }

        $descParts = array_filter([
            'Shop',
            $fullName !== '' ? $fullName : null,
            $city !== '' ? $city : null,
            $countryRaw !== '' ? $countryRaw : null,
            $phone !== '' ? $phone : null,
        ]);
        $description = Str::limit(implode(' · ', $descParts), 999);

        $form = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
            'description' => $description,
            'metadata[checkout_ref]' => $checkoutRef,
            'metadata[user_id]' => (string) $user->id,
            'metadata[ship_full_name]' => Str::limit($fullName, 500),
            'metadata[ship_phone]' => Str::limit($phone, 500),
            'metadata[ship_street]' => Str::limit($street, 500),
            'metadata[ship_line2]' => Str::limit($line2, 500),
            'metadata[ship_city]' => Str::limit($city, 500),
            'metadata[ship_state]' => Str::limit($state, 500),
            'metadata[ship_zip]' => Str::limit($zip, 500),
            'metadata[ship_country_raw]' => Str::limit($countryRaw, 500),
            'metadata[ship_country_iso]' => $countryCode,
            'metadata[ship_company]' => Str::limit($company, 500),
            'metadata[ship_email]' => Str::limit($shipEmail, 500),
            'metadata[account_name]' => Str::limit($accountName, 500),
            'metadata[account_phone]' => Str::limit($accountPhone, 500),
            'metadata[account_email]' => Str::limit($userEmail, 500),
            'metadata[cart_preview]' => Str::limit($cartPreview, 500),
            'metadata[order_total]' => (string) $total,
            'metadata[wallet_amount_applied]' => (string) round($walletApplied, 2),
            'metadata[currency]' => strtoupper($currency),
        ];

        if ($receiptEmail !== '') {
            $form['receipt_email'] = $receiptEmail;
        }

        // Shown on Stripe PaymentIntent / receipt; country must be ISO-3166-1 alpha-2
        $form['shipping[name]'] = Str::limit($fullName, 500);
        $form['shipping[phone]'] = Str::limit($phone, 20);
        $form['shipping[address][line1]'] = Str::limit($street, 500);
        if ($line2 !== '') {
            $form['shipping[address][line2]'] = Str::limit($line2, 500);
        }
        $form['shipping[address][city]'] = Str::limit($city, 100);
        if ($state !== '') {
            $form['shipping[address][state]'] = Str::limit($state, 100);
        }
        if ($zip !== '') {
            $form['shipping[address][postal_code]'] = Str::limit($zip, 20);
        }
        $form['shipping[address][country]'] = $countryCode;

        foreach ($form as $k => $v) {
            if (str_starts_with((string) $k, 'metadata[') && ($v === '' || $v === null)) {
                unset($form[$k]);
            }
        }

        $customerId = $this->findOrCreateStripeCustomer($secret, $user, [
            'full_name' => $fullName,
            'phone' => $phone,
            'street' => $street,
            'line2' => $line2,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'country' => $countryCode,
            'email' => $receiptEmail !== '' ? $receiptEmail : ($shipEmail !== '' ? $shipEmail : null),
        ]);
        if ($customerId !== null) {
            $form['customer'] = $customerId;
        }

        $resp = Http::withToken($secret)
            ->withHeaders(['Idempotency-Key' => 'smc_'.$checkoutRef])
            ->asForm()
            ->post('https://api.stripe.com/v1/payment_intents', $form);

        if (! $resp->successful()) {
            $msg = $resp->json('error.message') ?? $resp->body();
            Log::warning('Stripe PaymentIntent create failed', ['body' => $msg]);

            return $this->err('Could not start payment. Please try again.', 502);
        }

        $pi = $resp->json();
        $piId = $pi['id'] ?? null;
        $clientSecret = $pi['client_secret'] ?? null;
        if (! is_string($piId) || ! is_string($clientSecret)) {
            return $this->err('Invalid Stripe response.', 502);
        }

        ShopMobileCheckout::create([
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'checkout_ref' => $checkoutRef,
            'stripe_payment_intent_id' => $piId,
            'source' => $isBuyNow ? 'buy_now' : 'cart',
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'lines_json' => $lines,
            'shipping_json' => $shippingJson,
            'subtotal_amount' => $summary['subtotal'],
            'tax_amount' => $summary['tax'],
            'tax_percent' => $summary['tax_percent'],
            'shipping_amount' => $summary['shipping'],
            'total_amount' => $total,
            'wallet_amount_applied' => $walletApplied,
            'special_instructions' => $specialInstructions,
        ]);

        $data = [
            'client_secret' => $clientSecret,
            'payment_intent_id' => $piId,
            'publishable_key' => StripeCredentials::publishableKey(),
            'stripe_mode' => str_starts_with(StripeCredentials::secretKey(), 'sk_live_') ? 'live' : 'test',
            'order_total' => $total,
            'amount_due' => $cardTotal,
            'wallet_amount_applied' => $walletApplied,
        ];

        return [
            'ok' => true,
            'message' => 'Payment intent created.',
            'data' => $data,
        ];
    }

    /**
     * After Payment Sheet succeeds: create order if not already created (idempotent).
     *
     * @return array{ok: bool, message?: string, status?: int, data?: array}
     */
    public function confirmOrder(User $user, string $paymentIntentId): array
    {
        if (! StripeCredentials::isStripeUsableForCheckout()) {
            return $this->err('Stripe is not enabled or not configured.', 422);
        }

        $paymentIntentId = trim($paymentIntentId);
        if ($paymentIntentId === '' || ! str_starts_with($paymentIntentId, 'pi_')) {
            return $this->err('Invalid payment_intent_id.', 422);
        }

        $existing = Order::where('payment_reference', $paymentIntentId)
            ->where('payment_method', 'stripe')
            ->first();
        if ($existing) {
            return [
                'ok' => true,
                'message' => 'Order already confirmed.',
                'data' => $this->orderPayload($existing),
                'http_code' => 200,
            ];
        }

        $secret = StripeCredentials::secretKey();
        $resp = Http::withToken($secret)->get('https://api.stripe.com/v1/payment_intents/'.$paymentIntentId);
        if (! $resp->successful()) {
            $stripeError = $resp->json('error') ?? [];
            $stripeCode = (string) ($stripeError['code'] ?? '');
            $stripeType = (string) ($stripeError['type'] ?? '');
            $stripeMessage = (string) ($stripeError['message'] ?? '');

            if ($stripeCode === 'resource_missing' || str_contains(strtolower($stripeMessage), 'no such payment_intent')) {
                $mode = str_starts_with($secret, 'sk_live_') ? 'live' : 'test';

                return $this->err(
                    "Payment intent not found on Stripe {$mode} mode. Create a fresh payment intent and make sure app publishable key mode matches backend secret key mode.",
                    409
                );
            }

            Log::warning('Stripe payment intent verify failed', [
                'status' => $resp->status(),
                'stripe_code' => $stripeCode,
                'stripe_type' => $stripeType,
                'stripe_message' => $stripeMessage,
            ]);

            return $this->err('Could not verify payment with Stripe.', 502);
        }

        $pi = $resp->json();
        if (($pi['status'] ?? '') !== 'succeeded') {
            return $this->err('Payment is not completed yet.', 409);
        }

        $row = ShopMobileCheckout::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->first();

        if (! $row) {
            return $this->err('No pending checkout found for this payment. Create a new payment intent.', 404);
        }

        $piAmount = (int) ($pi['amount'] ?? 0);
        if ($piAmount !== (int) $row->amount_minor) {
            Log::warning('Stripe PI amount mismatch vs checkout row', ['pi' => $piAmount, 'row' => $row->amount_minor]);

            return $this->err('Payment amount mismatch. Contact support.', 409);
        }

        try {
            $order = DB::transaction(function () use ($row, $user, $paymentIntentId) {
                $locked = ShopMobileCheckout::query()
                    ->whereKey($row->id)
                    ->lockForUpdate()
                    ->first();
                if (! $locked || $locked->consumed_at !== null) {
                    $existingOrder = Order::where('payment_reference', $paymentIntentId)->first();
                    if ($existingOrder) {
                        return $existingOrder;
                    }
                    throw new \RuntimeException('Checkout already processed.');
                }

                $order = $this->buildPaidOrder($user, $locked, $paymentIntentId);
                $locked->forceFill(['consumed_at' => now()])->save();

                return $order;
            });
        } catch (\RuntimeException $e) {
            $existingOrder = Order::where('payment_reference', $paymentIntentId)->first();
            if ($existingOrder) {
                return [
                    'ok' => true,
                    'message' => 'Order already confirmed.',
                    'data' => $this->orderPayload($existingOrder),
                    'http_code' => 200,
                ];
            }

            return $this->err($e->getMessage(), 409);
        }

        $this->notifyAdminsNewOrder($order, (float) $order->total_amount, 'Stripe (mobile)');

        return [
            'ok' => true,
            'message' => 'Order placed successfully.',
            'data' => $this->orderPayload($order),
            'http_code' => 201,
        ];
    }

    /**
     * Webhook handler fragment: payment_intent.succeeded (no user context).
     */
    public function fulfillFromWebhookPaymentIntent(array $pi): void
    {
        $piId = $pi['id'] ?? null;
        if (! is_string($piId) || ($pi['status'] ?? '') !== 'succeeded') {
            return;
        }

        if (Order::where('payment_reference', $piId)->where('payment_method', 'stripe')->exists()) {
            return;
        }

        $row = ShopMobileCheckout::query()
            ->where('stripe_payment_intent_id', $piId)
            ->whereNull('consumed_at')
            ->first();

        if (! $row) {
            return;
        }

        $user = User::find($row->user_id);
        if (! $user) {
            return;
        }

        try {
            $order = DB::transaction(function () use ($row, $user, $piId, $pi) {
                $locked = ShopMobileCheckout::query()->whereKey($row->id)->lockForUpdate()->first();
                if (! $locked || $locked->consumed_at !== null) {
                    return null;
                }
                if (Order::where('payment_reference', $piId)->exists()) {
                    return null;
                }
                $piAmount = (int) ($pi['amount'] ?? 0);
                if ($piAmount !== (int) $locked->amount_minor) {
                    return null;
                }
                $created = $this->buildPaidOrder($user, $locked, $piId);
                $locked->forceFill(['consumed_at' => now()])->save();

                return $created;
            });
            if ($order instanceof Order) {
                $this->notifyAdminsNewOrder($order, (float) $order->total_amount, 'Stripe (webhook)');
            }
        } catch (\Throwable $e) {
            Log::error('Shop mobile checkout webhook fulfill failed: '.$e->getMessage());
        }
    }

    protected function buildPaidOrder(User $user, ShopMobileCheckout $row, string $paymentIntentId): Order
    {
        $isWalletPayment = str_starts_with($paymentIntentId, 'wallet:');
        $ship = $row->shipping_json;
        $street = trim((string) ($ship['street_address'] ?? ''));
        $line2 = trim((string) ($ship['line2'] ?? ''));
        $streetCombined = $street.($line2 !== '' ? "\n".$line2 : '');

        $address = UserAddress::create([
            'user_id' => $user->id,
            'type' => 'home',
            'full_name' => $ship['full_name'],
            'phone_number' => $ship['phone_number'],
            'street_address' => $streetCombined !== '' ? $streetCombined : $street,
            'city' => $ship['city'],
            'state' => $ship['state'] ?: null,
            'zip_code' => $ship['zip_code'] ?: null,
            'country' => $ship['country'],
            'is_default' => false,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'total_amount' => $row->total_amount,
            'wallet_amount_applied' => (float) ($row->wallet_amount_applied ?? 0),
            'subtotal_amount' => $row->subtotal_amount,
            'tax_amount' => $row->tax_amount,
            'tax_percent' => $row->tax_percent,
            'shipping_amount' => $row->shipping_amount,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => $isWalletPayment ? 'wallet' : 'stripe',
            'payment_reference' => $paymentIntentId,
            'transaction_id' => $paymentIntentId,
            'paid_at' => now(),
            'special_instructions' => $row->special_instructions,
        ]);

        foreach ($row->lines_json as $line) {
            $product = Product::find($line['product_id'] ?? null);
            if (! $product) {
                continue;
            }
            $qty = (int) ($line['quantity'] ?? 1);
            $unit = isset($line['unit_price']) ? (float) $line['unit_price'] : (float) $product->price;
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'price' => $unit,
                'subtotal' => round($unit * $qty, 2),
            ]);
        }

        if ($row->source === 'cart') {
            Cart::where('user_id', $user->id)->delete();
        }

        $walletApplied = (float) ($row->wallet_amount_applied ?? 0);
        if ($walletApplied > 0.0001) {
            app(ShopWalletRedemptionService::class)->redeem($user, $walletApplied, (int) $order->id, $order->publicOrderNumber());
            $order->wallet_redeemed_at = now();
            $order->save();
        }

        return $order;
    }

    protected function orderPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_number' => $order->publicOrderNumber(),
            'payment_status' => $order->payment_status,
            'total_amount' => (float) $order->total_amount,
            'wallet_amount_applied' => (float) ($order->wallet_amount_applied ?? 0),
        ];
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

    /**
     * Cancel Stripe PIs left open for a long time (abandoned checkouts / repeated testing) so the dashboard stays clean.
     */
    protected function cancelLongAbandonedMobileCheckouts(User $user, string $secret, int $olderThanMinutes): void
    {
        $rows = ShopMobileCheckout::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('created_at', '<', now()->subMinutes($olderThanMinutes))
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($rows as $row) {
            $piId = $row->stripe_payment_intent_id;
            if (is_string($piId) && str_starts_with($piId, 'pi_')) {
                Http::withToken($secret)->asForm()->post(
                    'https://api.stripe.com/v1/payment_intents/'.$piId.'/cancel',
                    []
                );
            }
            $row->delete();
        }
    }

    /**
     * If the app calls payment-intent again with the same cart + shipping within a short window, return the same client_secret instead of creating another Stripe object.
     *
     * @return array{ok: true, message: string, data: array{client_secret: string}}|null
     */
    protected function maybeReuseRecentPaymentIntent(User $user, string $secret, string $fingerprint, int $amountMinor): ?array
    {
        $row = ShopMobileCheckout::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->whereNull('consumed_at')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->orderByDesc('id')
            ->first();

        if (! $row || ! is_string($row->stripe_payment_intent_id) || $row->stripe_payment_intent_id === '') {
            return null;
        }

        if ((int) $row->amount_minor !== $amountMinor) {
            return null;
        }

        $resp = Http::withToken($secret)->get('https://api.stripe.com/v1/payment_intents/'.$row->stripe_payment_intent_id);
        if (! $resp->successful()) {
            return null;
        }

        $pi = $resp->json();
        $status = $pi['status'] ?? '';
        if (! in_array($status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
            return null;
        }

        if ((int) ($pi['amount'] ?? 0) !== $amountMinor) {
            return null;
        }

        $clientSecret = $pi['client_secret'] ?? null;
        if (! is_string($clientSecret) || $clientSecret === '') {
            return null;
        }

        return [
            'ok' => true,
            'message' => 'Payment intent reused (same cart within 10 minutes — avoids duplicate Incomplete rows in Stripe).',
            'data' => ['client_secret' => $clientSecret],
        ];
    }

    /**
     * Merge camelCase / alternate keys from the mobile app into canonical `shipping.*` keys before validation.
     */
    protected function mergeNormalizedShipping(Request $request): void
    {
        $s = $request->input('shipping');
        if (! is_array($s)) {
            return;
        }

        $canonical = [
            'full_name' => self::pickShippingString($s, ['full_name', 'fullName']),
            'phone' => self::pickShippingString($s, ['phone', 'phone_number', 'phoneNumber', 'mobile', 'mobile_number']),
            'street' => self::pickShippingString($s, ['street', 'street_address', 'address', 'line1', 'addressLine1']),
            'line2' => self::pickShippingString($s, ['line2', 'address_line_2', 'addressLine2', 'apartment', 'suite', 'unit']),
            'city' => self::pickShippingString($s, ['city']),
            'state' => self::pickShippingString($s, ['state', 'province', 'region']),
            'zip_code' => self::pickShippingString($s, ['zip_code', 'zipCode', 'postal_code', 'postalCode']),
            'country' => self::pickShippingString($s, ['country', 'country_code', 'countryCode']),
            'company' => self::pickShippingString($s, ['company', 'organization', 'business_name']),
            'email' => self::pickShippingString($s, ['email', 'contact_email']),
        ];

        $request->merge(['shipping' => array_merge($s, array_filter($canonical, fn ($v) => $v !== ''))]);
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected static function pickShippingString(array $shipping, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $shipping)) {
                continue;
            }
            $v = $shipping[$key];
            if ($v === null) {
                continue;
            }
            $t = is_string($v) ? trim($v) : (is_numeric($v) ? trim((string) $v) : '');
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Cart>|\Illuminate\Database\Eloquent\Collection  $items
     */
    protected function cartPreviewLabel($items): string
    {
        $parts = [];
        foreach ($items as $cart) {
            if ($cart->product === null) {
                continue;
            }
            $parts[] = $cart->product->name.' ×'.(int) $cart->quantity;
        }

        return Str::limit(implode(', ', $parts), 500);
    }

    /**
     * Stable Stripe Customer per app user (idempotent create) + latest name/phone/address update.
     *
     * @param  array{full_name: string, phone: string, street: string, line2: string, city: string, state: string, zip: string, country: string, email: ?string}  $addr
     */
    protected function findOrCreateStripeCustomer(string $secret, User $user, array $addr): ?string
    {
        $idemp = 'tandil_cst_uid_'.$user->id;

        $createBody = ['metadata[app_user_id]' => (string) $user->id];
        $email = $addr['email'] ?? null;
        if (is_string($email) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $createBody['email'] = $email;
        }

        $create = Http::withToken($secret)
            ->withHeaders(['Idempotency-Key' => $idemp])
            ->asForm()
            ->post('https://api.stripe.com/v1/customers', $createBody);

        if (! $create->successful()) {
            Log::warning('Stripe customer create failed', ['body' => $create->body()]);

            return null;
        }

        $id = $create->json('id');
        if (! is_string($id) || $id === '') {
            return null;
        }

        $update = [
            'name' => Str::limit($addr['full_name'], 256),
            'phone' => Str::limit($addr['phone'], 20),
            'address[line1]' => Str::limit($addr['street'], 500),
            'address[city]' => Str::limit($addr['city'], 100),
            'address[country]' => $addr['country'],
        ];
        if ($addr['line2'] !== '') {
            $update['address[line2]'] = Str::limit($addr['line2'], 500);
        }
        if ($addr['state'] !== '') {
            $update['address[state]'] = Str::limit($addr['state'], 100);
        }
        if ($addr['zip'] !== '') {
            $update['address[postal_code]'] = Str::limit($addr['zip'], 20);
        }

        $patch = Http::withToken($secret)->asForm()->post('https://api.stripe.com/v1/customers/'.$id, $update);
        if (! $patch->successful()) {
            Log::warning('Stripe customer update failed', ['customer' => $id, 'body' => $patch->body()]);
        }

        return $id;
    }

    /**
     * Stripe expects two-letter ISO country (e.g. AE, US). Checkout UI may send "UAE".
     */
    protected static function stripeCountryCode(string $input): string
    {
        $t = trim($input);
        if (strlen($t) === 2 && ctype_alpha($t)) {
            return strtoupper($t);
        }

        $map = [
            'uae' => 'AE',
            'united arab emirates' => 'AE',
            'united states' => 'US',
            'usa' => 'US',
            'india' => 'IN',
            'pakistan' => 'PK',
            'saudi arabia' => 'SA',
            'ksa' => 'SA',
            'united kingdom' => 'GB',
            'uk' => 'GB',
        ];

        return $map[strtolower($t)] ?? 'AE';
    }

    /**
     * @return array{ok: false, message: string, status: int}
     */
    protected function err(string $message, int $status): array
    {
        return ['ok' => false, 'message' => $message, 'status' => $status];
    }
}
