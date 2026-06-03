<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Cart;
use App\Models\Coupon;
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
        CartController::normalizeCheckoutCouponInput($request);

        $request->validate([
            'is_buy_now' => 'sometimes|boolean',
            'product_id' => 'required_if:is_buy_now,true|nullable|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
            'use_wallet' => 'sometimes|boolean',
            'wallet_amount' => 'nullable|numeric|min:0',
            'code' => 'nullable|string|max:64',
            'coupon_code' => 'nullable|string|max:64',
            'clear_coupon' => 'sometimes|boolean',
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
            'option_ids' => 'nullable|array',
            'option_ids.*' => 'integer|exists:product_options,id',
        ]);

        $isBuyNow = $request->boolean('is_buy_now');
        if ($isBuyNow && ! $request->filled('product_id')) {
            return $this->err('product_id is required when is_buy_now is true.', 422);
        }

        // Same totals as GET /api/shop/order-summary (cart, buy-now product_id/qty, coupon_code, catalog discount).
        $pack = CartController::checkoutTotalsForRequest($request, $user);
        if ($pack['error'] !== null) {
            return $this->err($pack['error'], 422);
        }

        $preview = $pack['cart_preview'];
        if ($preview['items']->isEmpty()) {
            return $this->err('Your cart is empty. Add items or use buy now with a product.', 422);
        }

        $summary = CartController::mergeWalletPreviewIntoOrderSummary($pack['order_summary'], $request, $user);
        CartController::addCheckoutUiAliases($summary);

        $couponId = $pack['coupon_id'];
        $couponCodeStored = $pack['coupon_code'];
        $couponMerchDisc = (float) $pack['coupon_merchandise_discount'];
        $couponShipDisc = (float) $pack['coupon_shipping_discount'];

        $total = (float) $summary['total'];
        $currency = strtolower((string) config('shop.currency', CartController::CURRENCY));

        $lines = [];
        foreach ($preview['items'] as $cart) {
            if ($cart->product === null) {
                continue;
            }
            $lines[] = $cart->checkoutLinePayload();
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
        $walletApplied = (float) ($summary['wallet_amount_applied'] ?? 0);
        $cardTotal = (float) ($summary['amount_due'] ?? $summary['total']);

        if ($cardTotal <= 0.0001 && $walletApplied > 0.0001) {
            try {
                $order = DB::transaction(function () use (
                    $user,
                    $preview,
                    $specialInstructions,
                    $summary,
                    $total,
                    $walletApplied,
                    $isBuyNow,
                    $currency,
                    $shippingJson,
                    $couponId,
                    $couponCodeStored,
                    $couponMerchDisc,
                    $couponShipDisc
                ) {
                    $linesW = [];
                    foreach ($preview['items'] as $cart) {
                        if ($cart->product === null) {
                            continue;
                        }
                        $linesW[] = $cart->checkoutLinePayload();
                    }
                    $row = new ShopMobileCheckout([
                        'user_id' => $user->id,
                        'coupon_id' => $couponId,
                        'coupon_code' => $couponCodeStored,
                        'coupon_merchandise_discount' => $couponMerchDisc,
                        'coupon_shipping_discount' => $couponShipDisc,
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
            'coupon_id' => $couponId,
            'coupon_merch' => round($couponMerchDisc, 2),
            'coupon_ship' => round($couponShipDisc, 2),
        ], JSON_THROW_ON_ERROR));

        $reconciled = $this->reconcileActivePaymentIntentWithCheckout(
            $request,
            $user,
            $pack,
            $summary,
            $amountMinor,
            $total,
            $cardTotal,
            $walletApplied,
            $currency,
            $preview,
            $isBuyNow,
            $secret,
            $couponId,
            $couponCodeStored
        );
        if ($reconciled !== null) {
            return $reconciled;
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
        if ($couponCodeStored !== null && $couponCodeStored !== '') {
            $form['metadata[coupon_code]'] = Str::limit($couponCodeStored, 40);
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

        $checkoutPayload = [
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'checkout_ref' => $checkoutRef,
            'stripe_payment_intent_id' => $piId,
            'coupon_id' => $couponId,
            'coupon_code' => $couponCodeStored,
            'coupon_merchandise_discount' => $couponMerchDisc,
            'coupon_shipping_discount' => $couponShipDisc,
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
            'consumed_at' => null,
        ];

        $existingRow = ShopMobileCheckout::query()
            ->where('stripe_payment_intent_id', $piId)
            ->first();

        if ($existingRow !== null) {
            $existingRow->update($checkoutPayload);
        } else {
            ShopMobileCheckout::create($checkoutPayload);
        }

        return $this->formatPaymentIntentApiResponse(
            $clientSecret,
            $piId,
            $summary,
            $total,
            $cardTotal,
            $walletApplied,
            $amountMinor,
            $currency,
            $preview,
            $request,
            $isBuyNow,
            'Payment intent created.'
        );
    }

    /**
     * Pending PI exists (e.g. opened before Apply): force Stripe amount + DB row to match order-summary now.
     *
     * @param  array<string, mixed>  $pack
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $preview
     * @return array{ok: true, message: string, data: array<string, mixed>}|null
     */
    private function reconcileActivePaymentIntentWithCheckout(
        Request $request,
        User $user,
        array $pack,
        array $summary,
        int $amountMinor,
        float $total,
        float $cardTotal,
        float $walletApplied,
        string $currency,
        array $preview,
        bool $isBuyNow,
        string $secret,
        ?int $couponId,
        ?string $couponCodeStored
    ): ?array {
        $piId = $this->findActivePaymentIntentIdForUser((int) $user->id);
        if ($piId === null) {
            return null;
        }

        $row = ShopMobileCheckout::query()
            ->where('user_id', $user->id)
            ->where('stripe_payment_intent_id', $piId)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $stripeMatches = $this->paymentIntentAmountMatches($secret, $piId, $amountMinor);
        $rowMatches = (int) $row->amount_minor === $amountMinor
            && (int) ($row->coupon_id ?? 0) === (int) ($couponId ?? 0)
            && strtoupper(trim((string) ($row->coupon_code ?? ''))) === strtoupper(trim((string) ($couponCodeStored ?? '')));

        if ($stripeMatches && $rowMatches) {
            $clientSecret = $this->fetchPaymentIntentClientSecret($secret, $piId);
            if ($clientSecret === null) {
                return null;
            }

            $this->persistCheckoutPaymentRow(
                $row,
                $pack,
                $summary,
                $piId,
                $clientSecret,
                $amountMinor,
                $total,
                $cardTotal,
                $walletApplied,
                $currency,
                false
            );

            return $this->formatPaymentIntentApiResponse(
                $clientSecret,
                $piId,
                $summary,
                $total,
                $cardTotal,
                $walletApplied,
                $amountMinor,
                $currency,
                $preview,
                $request,
                $isBuyNow,
                'Payment intent matches order summary.',
                false
            );
        }

        $request->merge(['payment_intent_id' => $piId]);
        $synced = $this->syncPaymentIntentAfterCoupon($request, $user, $pack, $summary);
        if (! ($synced['ok'] ?? false)) {
            Log::warning('Could not reconcile Stripe PI with order summary; canceling stale PI.', [
                'user_id' => $user->id,
                'pi' => $piId,
                'amount_minor' => $amountMinor,
            ]);
            $this->cancelStripePaymentIntent($secret, $piId);
            $row->delete();

            return null;
        }

        $syncData = is_array($synced['data'] ?? null) ? $synced['data'] : [];

        return $this->formatPaymentIntentApiResponse(
            (string) ($syncData['client_secret'] ?? ''),
            (string) ($syncData['payment_intent_id'] ?? $piId),
            $summary,
            $total,
            $cardTotal,
            $walletApplied,
            $amountMinor,
            $currency,
            $preview,
            $request,
            $isBuyNow,
            'Payment intent updated to match order summary.',
            true
        );
    }

    private function fetchPaymentIntentClientSecret(string $secret, string $piId): ?string
    {
        $resp = Http::withToken($secret)->get('https://api.stripe.com/v1/payment_intents/'.$piId);
        if (! $resp->successful()) {
            return null;
        }

        $clientSecret = $resp->json('client_secret');
        if (! is_string($clientSecret) || $clientSecret === '') {
            return null;
        }

        $status = (string) ($resp->json('status') ?? '');
        if (! in_array($status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
            return null;
        }

        return $clientSecret;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $preview
     * @return array{ok: true, message: string, data: array<string, mixed>}
     */
    private function formatPaymentIntentApiResponse(
        string $clientSecret,
        string $paymentIntentId,
        array $summary,
        float $total,
        float $cardTotal,
        float $walletApplied,
        int $amountMinor,
        string $currency,
        array $preview,
        Request $request,
        bool $isBuyNow,
        string $message,
        bool $reinitializePaymentSheet = false
    ): array {
        $previewQty = 0;
        $firstLine = $preview['items']->first();
        if ($firstLine !== null) {
            $previewQty = (int) $firstLine->quantity;
        }

        $countryCode = 'AE';
        $ship = $request->input('shipping');
        if (is_array($ship)) {
            $countryCode = self::stripeCountryCode(trim((string) ($ship['country'] ?? '')));
        }

        return [
            'ok' => true,
            'message' => $message,
            'data' => [
                'client_secret' => $clientSecret,
                'payment_intent_id' => $paymentIntentId,
                'publishable_key' => StripeCredentials::publishableKey(),
                'stripe_mode' => str_starts_with(StripeCredentials::secretKey(), 'sk_live_') ? 'live' : 'test',
                'shipping_country_iso' => $countryCode,
                'order_total' => $total,
                'amount_due' => $cardTotal,
                'wallet_amount_applied' => $walletApplied,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency),
                'order_summary' => $summary,
                'preview_subtotal' => (float) ($preview['subtotal'] ?? 0),
                'preview_quantity' => $previewQty,
                'checkout_source' => $request->filled('product_id') ? 'product_buy_now' : ($isBuyNow ? 'buy_now' : 'cart'),
                'reinitialize_payment_sheet' => $reinitializePaymentSheet,
            ],
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

        $couponDiscountTotal = round(
            (float) ($row->coupon_merchandise_discount ?? 0) + (float) ($row->coupon_shipping_discount ?? 0),
            2
        );

        $order = Order::create([
            'user_id' => $user->id,
            'coupon_id' => $row->coupon_id,
            'coupon_code' => $row->coupon_code,
            'coupon_discount_amount' => $couponDiscountTotal,
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
            'coupon_code' => $order->coupon_code,
            'coupon_discount_amount' => (float) ($order->coupon_discount_amount ?? 0),
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
            'client_secret' => $clientSecret,
            'payment_intent_id' => $row->stripe_payment_intent_id,
            'message' => 'Payment intent reused (same cart within 10 minutes — avoids duplicate Incomplete rows in Stripe).',
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

        $tl = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
        // Remove common wrappers: "متحدہ عرب امارات (UAE)" => "متحدہ عرب امارات uae"
        $tl = preg_replace('/[()\\[\\]{}]/u', ' ', $tl) ?? $tl;
        $tl = trim(preg_replace('/\\s+/u', ' ', $tl) ?? $tl);

        $map = [
            'uae' => 'AE',
            'united arab emirates' => 'AE',
            'متحدہ عرب امارات' => 'AE',
            'الامارات العربية المتحدة' => 'AE',
            'الإمارات العربية المتحدة' => 'AE',
            'united states' => 'US',
            'usa' => 'US',
            'india' => 'IN',
            'pakistan' => 'PK',
            'saudi arabia' => 'SA',
            'ksa' => 'SA',
            'united kingdom' => 'GB',
            'uk' => 'GB',
        ];

        if (isset($map[$tl])) {
            return $map[$tl];
        }
        if (str_contains($tl, 'uae') || str_contains($tl, 'emirates') || str_contains($tl, 'امارات') || str_contains($tl, 'الإمارات')) {
            return 'AE';
        }

        return 'AE';
    }

    /**
     * @return array{ok: false, message: string, status: int}
     */
    /**
     * After POST /api/shop/coupons/apply — update Stripe PI amount to match discounted total.
     *
     * @param  array{
     *   coupon_id: ?int,
     *   coupon_code: ?string,
     *   coupon_merchandise_discount: float,
     *   coupon_shipping_discount: float,
     *   order_summary: array<string, mixed>
     * }  $pack
     * @param  array<string, mixed>  $orderSummary
     * @return array{ok: bool, message?: string, status?: int, data?: array<string, mixed>}
     */
    /**
     * Latest unconsumed mobile checkout PaymentIntent for this user (last 24h).
     */
    public function findActivePaymentIntentIdForUser(int $userId): ?string
    {
        $piId = ShopMobileCheckout::query()
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->whereNotNull('stripe_payment_intent_id')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->value('stripe_payment_intent_id');

        return is_string($piId) && $piId !== '' && str_starts_with($piId, 'pi_') ? $piId : null;
    }

    /**
     * Sync Stripe after coupon apply: update amount, verify, or recreate PI so the app gets a fresh client_secret.
     */
    public function syncPaymentIntentAfterCoupon(Request $request, User $user, array $pack, array $orderSummary): array
    {
        if (! StripeCredentials::isStripeUsableForCheckout()) {
            return $this->err('Stripe is not enabled or not configured.', 422);
        }

        $piId = trim((string) $request->input('payment_intent_id', ''));
        if ($piId === '' || ! str_starts_with($piId, 'pi_')) {
            return $this->err('Invalid payment_intent_id.', 422);
        }

        $row = ShopMobileCheckout::query()
            ->where('user_id', $user->id)
            ->where('stripe_payment_intent_id', $piId)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            return $this->err('Payment session not found. Call POST /api/shop/checkout/stripe/payment-intent with coupon_code after applying the coupon.', 404);
        }

        [$amountMinor, $cardTotal, $walletApplied, $total, $currency] = $this->resolveCardAmountFromOrderSummary(
            $request,
            $user,
            $orderSummary,
            $row
        );

        if ($amountMinor === null) {
            return $this->err('Order total is too low for card payment.', 422);
        }

        $secret = StripeCredentials::secretKey();
        $updated = $this->postPaymentIntentAmount($secret, $piId, $amountMinor);
        if ($updated !== null && $this->paymentIntentAmountMatches($secret, $piId, $amountMinor)) {
            return $this->persistCheckoutPaymentRow(
                $row,
                $pack,
                $orderSummary,
                $piId,
                $updated['client_secret'],
                $amountMinor,
                $total,
                $cardTotal,
                $walletApplied,
                $currency,
                false
            );
        }

        Log::info('Recreating Stripe PaymentIntent after coupon apply', [
            'old_pi' => $piId,
            'amount_minor' => $amountMinor,
        ]);

        return $this->recreatePaymentIntentForCoupon($secret, $row, $user, $pack, $orderSummary, $amountMinor, $total, $cardTotal, $walletApplied, $currency);
    }

    /**
     * @deprecated Use syncPaymentIntentAfterCoupon
     */
    public function updatePaymentIntentForAppliedCoupon(Request $request, User $user, array $pack, array $orderSummary): array
    {
        return $this->syncPaymentIntentAfterCoupon($request, $user, $pack, $orderSummary);
    }

    /**
     * @return array{0: ?int, 1: float, 2: float, 3: float, 4: string}
     */
    private function resolveCardAmountFromOrderSummary(
        Request $request,
        User $user,
        array $orderSummary,
        ShopMobileCheckout $row
    ): array {
        $total = (float) ($orderSummary['total'] ?? 0);
        $currency = strtolower((string) ($row->currency ?: config('shop.currency', CartController::CURRENCY)));

        $user->refresh();
        $walletRequested = max(0, (float) $request->input('wallet_amount', 0));
        if ($request->boolean('use_wallet') && $walletRequested <= 0) {
            $walletRequested = (float) ($user->wallet_balance ?? 0);
        }
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

        $amountMinor = (int) round($cardTotal * 100);
        if ($currency === 'aed' && $amountMinor > 0 && $amountMinor < self::MIN_AMOUNT_MINOR_AED) {
            return [null, $cardTotal, $walletApplied, $total, $currency];
        }
        if ($amountMinor > 0 && $amountMinor < 50) {
            return [null, $cardTotal, $walletApplied, $total, $currency];
        }

        return [$amountMinor, $cardTotal, $walletApplied, $total, $currency];
    }

    /**
     * @return array{client_secret: string}|null
     */
    private function postPaymentIntentAmount(string $secret, string $piId, int $amountMinor): ?array
    {
        $resp = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/payment_intents/'.$piId, [
                'amount' => $amountMinor,
            ]);

        if (! $resp->successful()) {
            Log::warning('Stripe PaymentIntent amount update failed', [
                'pi' => $piId,
                'body' => $resp->json('error.message') ?? $resp->body(),
            ]);

            return null;
        }

        $pi = $resp->json();
        $status = (string) ($pi['status'] ?? '');
        if (! in_array($status, ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'], true)) {
            return null;
        }

        $clientSecret = $pi['client_secret'] ?? null;
        if (! is_string($clientSecret) || $clientSecret === '') {
            return null;
        }

        return ['client_secret' => $clientSecret];
    }

    private function paymentIntentAmountMatches(string $secret, string $piId, int $amountMinor): bool
    {
        $resp = Http::withToken($secret)->get('https://api.stripe.com/v1/payment_intents/'.$piId);
        if (! $resp->successful()) {
            return false;
        }

        return (int) ($resp->json('amount') ?? -1) === $amountMinor;
    }

    private function cancelStripePaymentIntent(string $secret, string $piId): void
    {
        if ($piId === '' || ! str_starts_with($piId, 'pi_')) {
            return;
        }

        Http::withToken($secret)->asForm()->post(
            'https://api.stripe.com/v1/payment_intents/'.$piId.'/cancel',
            []
        );
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}
     */
    private function persistCheckoutPaymentRow(
        ShopMobileCheckout $row,
        array $pack,
        array $orderSummary,
        string $piId,
        string $clientSecret,
        int $amountMinor,
        float $total,
        float $cardTotal,
        float $walletApplied,
        string $currency,
        bool $recreated
    ): array {
        $couponCode = $pack['coupon_code'] ?? null;
        $lines = is_array($row->lines_json) ? $row->lines_json : [];
        $ship = is_array($row->shipping_json) ? $row->shipping_json : [];
        $fingerprint = hash('sha256', json_encode([
            'lines' => $lines,
            'amount_minor' => $amountMinor,
            'wallet_applied' => round($walletApplied, 2),
            'ship' => $ship,
            'coupon_id' => $pack['coupon_id'] ?? null,
            'coupon_merch' => round((float) ($pack['coupon_merchandise_discount'] ?? 0), 2),
        ], JSON_THROW_ON_ERROR));

        $row->update([
            'fingerprint' => $fingerprint,
            'stripe_payment_intent_id' => $piId,
            'coupon_id' => $pack['coupon_id'] ?? null,
            'coupon_code' => $couponCode,
            'coupon_merchandise_discount' => (float) ($pack['coupon_merchandise_discount'] ?? 0),
            'coupon_shipping_discount' => (float) ($pack['coupon_shipping_discount'] ?? 0),
            'amount_minor' => $amountMinor,
            'subtotal_amount' => $orderSummary['subtotal'] ?? $row->subtotal_amount,
            'tax_amount' => $orderSummary['tax'] ?? $row->tax_amount,
            'tax_percent' => $orderSummary['tax_percent'] ?? $row->tax_percent,
            'shipping_amount' => $orderSummary['shipping'] ?? $row->shipping_amount,
            'total_amount' => $total,
            'wallet_amount_applied' => $walletApplied,
        ]);

        return [
            'ok' => true,
            'data' => [
                'payment_intent_id' => $piId,
                'client_secret' => $clientSecret,
                'publishable_key' => StripeCredentials::publishableKey(),
                'order_total' => $total,
                'amount_due' => $cardTotal,
                'amount_minor' => $amountMinor,
                'wallet_amount_applied' => $walletApplied,
                'currency' => strtoupper($currency),
                'reinitialize_payment_sheet' => true,
                'payment_intent_recreated' => $recreated,
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, message: string, status: int}
     */
    private function recreatePaymentIntentForCoupon(
        string $secret,
        ShopMobileCheckout $row,
        User $user,
        array $pack,
        array $orderSummary,
        int $amountMinor,
        float $total,
        float $cardTotal,
        float $walletApplied,
        string $currency
    ): array {
        $oldPiId = $row->stripe_payment_intent_id;
        if (is_string($oldPiId) && str_starts_with($oldPiId, 'pi_')) {
            Http::withToken($secret)->asForm()->post(
                'https://api.stripe.com/v1/payment_intents/'.$oldPiId.'/cancel',
                []
            );
        }

        $ship = is_array($row->shipping_json) ? $row->shipping_json : [];
        $fullName = trim((string) ($ship['full_name'] ?? ''));
        $phone = trim((string) ($ship['phone_number'] ?? $ship['phone'] ?? ''));
        $street = trim((string) ($ship['street_address'] ?? $ship['street'] ?? ''));
        $line2 = trim((string) ($ship['line2'] ?? ''));
        $city = trim((string) ($ship['city'] ?? ''));
        $state = trim((string) ($ship['state'] ?? ''));
        $zip = trim((string) ($ship['zip_code'] ?? ''));
        $countryRaw = trim((string) ($ship['country'] ?? ''));
        $countryCode = self::stripeCountryCode($countryRaw);
        $couponCode = strtoupper(trim((string) ($pack['coupon_code'] ?? '')));

        $checkoutRef = (string) Str::ulid();
        $form = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
            'description' => Str::limit('Shop · '.$fullName, 999),
            'metadata[checkout_ref]' => $checkoutRef,
            'metadata[user_id]' => (string) $user->id,
            'metadata[order_total]' => (string) $total,
            'metadata[wallet_amount_applied]' => (string) round($walletApplied, 2),
            'metadata[currency]' => strtoupper($currency),
            'shipping[name]' => Str::limit($fullName, 500),
            'shipping[phone]' => Str::limit($phone, 20),
            'shipping[address][line1]' => Str::limit($street, 500),
            'shipping[address][city]' => Str::limit($city, 100),
            'shipping[address][country]' => $countryCode,
        ];
        if ($line2 !== '') {
            $form['shipping[address][line2]'] = Str::limit($line2, 500);
        }
        if ($state !== '') {
            $form['shipping[address][state]'] = Str::limit($state, 100);
        }
        if ($zip !== '') {
            $form['shipping[address][postal_code]'] = Str::limit($zip, 20);
        }
        if ($couponCode !== '') {
            $form['metadata[coupon_code]'] = Str::limit($couponCode, 40);
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
            'email' => trim((string) ($user->email ?? '')),
        ]);
        if ($customerId !== null) {
            $form['customer'] = $customerId;
        }

        $resp = Http::withToken($secret)
            ->withHeaders(['Idempotency-Key' => 'smc_coupon_'.$checkoutRef])
            ->asForm()
            ->post('https://api.stripe.com/v1/payment_intents', $form);

        if (! $resp->successful()) {
            $msg = $resp->json('error.message') ?? $resp->body();
            Log::warning('Stripe PaymentIntent recreate after coupon failed', ['body' => $msg]);

            return $this->err('Could not refresh payment amount. Call payment-intent again with coupon_code.', 502);
        }

        $pi = $resp->json();
        $newPiId = $pi['id'] ?? null;
        $clientSecret = $pi['client_secret'] ?? null;
        if (! is_string($newPiId) || ! is_string($clientSecret) || $clientSecret === '') {
            return $this->err('Invalid Stripe response.', 502);
        }

        $row->checkout_ref = $checkoutRef;

        return $this->persistCheckoutPaymentRow(
            $row,
            $pack,
            $orderSummary,
            $newPiId,
            $clientSecret,
            $amountMinor,
            $total,
            $cardTotal,
            $walletApplied,
            $currency,
            true
        );
    }

    protected function err(string $message, int $status): array
    {
        return ['ok' => false, 'message' => $message, 'status' => $status];
    }
}
