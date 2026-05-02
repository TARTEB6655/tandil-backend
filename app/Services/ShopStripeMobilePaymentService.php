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

        $request->validate([
            'is_buy_now' => 'sometimes|boolean',
            'product_id' => 'required_if:is_buy_now,true|nullable|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
            'shipping' => 'required|array',
            'shipping.full_name' => 'required|string|max:255',
            'shipping.phone' => 'required|string|max:30',
            'shipping.street' => 'required|string|max:500',
            'shipping.city' => 'required|string|max:100',
            'shipping.state' => 'nullable|string|max:100',
            'shipping.zip_code' => 'nullable|string|max:20',
            'shipping.country' => 'required|string|max:100',
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
        $amountMinor = (int) round($total * 100);
        $currency = strtolower((string) config('shop.currency', CartController::CURRENCY));

        if ($currency === 'aed' && $amountMinor < self::MIN_AMOUNT_MINOR_AED) {
            return $this->err('Order total is below the minimum for card payment (2.00 AED).', 422);
        }
        if ($amountMinor < 50) {
            return $this->err('Order total is too low for card payment.', 422);
        }

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
            'city' => $ship['city'],
            'state' => $ship['state'] ?? '',
            'zip_code' => $ship['zip_code'] ?? '',
            'country' => $ship['country'],
        ];

        $checkoutRef = (string) Str::ulid();
        $secret = StripeCredentials::secretKey();

        $fullName = trim((string) $ship['full_name']);
        $phone = trim((string) $ship['phone']);
        $countryCode = self::stripeCountryCode((string) ($ship['country'] ?? ''));

        $form = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
            'description' => 'Shop — '.Str::limit($fullName, 200),
            'metadata[checkout_ref]' => $checkoutRef,
            'metadata[user_id]' => (string) $user->id,
            'metadata[customer_name]' => Str::limit($fullName, 450),
            'metadata[customer_phone]' => Str::limit($phone, 40),
        ];

        $userEmail = trim((string) ($user->email ?? ''));
        if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $form['metadata[customer_email]'] = Str::limit($userEmail, 450);
            $form['receipt_email'] = $userEmail;
        }

        // Shown on Stripe PaymentIntent / receipt; country must be ISO-3166-1 alpha-2
        $form['shipping[name]'] = Str::limit($fullName, 500);
        $form['shipping[phone]'] = Str::limit($phone, 20);
        $form['shipping[address][line1]'] = Str::limit((string) $ship['street'], 500);
        $form['shipping[address][city]'] = Str::limit((string) $ship['city'], 100);
        if (! empty($ship['state'])) {
            $form['shipping[address][state]'] = Str::limit((string) $ship['state'], 100);
        }
        $zip = trim((string) ($ship['zip_code'] ?? ''));
        if ($zip !== '') {
            $form['shipping[address][postal_code]'] = Str::limit($zip, 20);
        }
        $form['shipping[address][country]'] = $countryCode;

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
        ]);

        $data = [
            'client_secret' => $clientSecret,
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
        $ship = $row->shipping_json;
        $address = UserAddress::create([
            'user_id' => $user->id,
            'type' => 'home',
            'full_name' => $ship['full_name'],
            'phone_number' => $ship['phone_number'],
            'street_address' => $ship['street_address'],
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
            'subtotal_amount' => $row->subtotal_amount,
            'tax_amount' => $row->tax_amount,
            'tax_percent' => $row->tax_percent,
            'shipping_amount' => $row->shipping_amount,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'payment_reference' => $paymentIntentId,
            'transaction_id' => $paymentIntentId,
            'paid_at' => now(),
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

        return $order;
    }

    protected function orderPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_number' => 'order_'.str_pad((string) $order->id, 3, '0', STR_PAD_LEFT),
            'payment_status' => $order->payment_status,
            'total_amount' => (float) $order->total_amount,
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
        } catch (\Exception $e) {
            Log::error('Failed to send order notification: '.$e->getMessage());
        }
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
