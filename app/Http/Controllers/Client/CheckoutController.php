<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CartController as ShopCartController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayPalService;
use App\Services\ShopCouponService;
use App\Services\StripeCheckoutSessionService;
use App\Support\OrderPaidSideEffects;
use App\Support\RefundPolicy;
use App\Support\StripeCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(
        protected PayPalService $paypal,
        protected ShopCouponService $couponService,
        protected StripeCheckoutSessionService $stripeCheckout
    ) {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $cartItems = Cart::where('user_id', $user->id)
            ->with(['product.category', 'product.primaryImage', 'product.services', 'product.optionGroups.options'])
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('client.cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = round($cartItems->sum(function ($item) {
            if (! $item->product) {
                return 0;
            }

            return $item->quantity * $item->lineUnitPrice();
        }), 2);

        $couponCode = strtoupper(trim((string) request()->query('coupon_code', old('coupon_code', ''))));
        $couponResult = null;
        if ($couponCode !== '') {
            $categoryIds = $cartItems
                ->map(fn ($item) => $item->product?->category_id)
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $baseShipping = ShopCartController::resolveBaseShippingForCart($cartItems);
            $couponResult = $this->couponService->preview(
                $couponCode,
                $subtotal,
                0,
                (int) $user->id,
                $categoryIds,
                'products',
                [],
                $baseShipping
            );
        }

        $orderSummary = (is_array($couponResult) && ($couponResult['ok'] ?? false) && is_array($couponResult['order_summary'] ?? null))
            ? ShopCartController::mergeCategoryShippingIntoSummary($couponResult['order_summary'], $cartItems)
            : ShopCartController::buildOrderSummary($subtotal, 0, $cartItems);

        $tax = $orderSummary['tax'];
        $shipping = $orderSummary['shipping'];
        $total = $orderSummary['total'];
        $taxPercent = $orderSummary['tax_percent'];
        $shippingLabel = $orderSummary['shipping_label'];
        $couponDiscount = (float) ($orderSummary['coupon_discount'] ?? 0);
        $appliedCouponCode = (string) ($orderSummary['coupon_code'] ?? '');
        $couponError = null;
        if ($couponCode !== '' && (! is_array($couponResult) || ! ($couponResult['ok'] ?? false))) {
            $couponError = (string) ($couponResult['message'] ?? 'Invalid coupon code.');
        }

        $refundPolicy = RefundPolicy::policyForApi();

        $categoryShippingBreakdown = $orderSummary['category_shipping_breakdown'] ?? [];

        return view('client.checkout.index', compact(
            'cartItems',
            'subtotal',
            'tax',
            'shipping',
            'total',
            'taxPercent',
            'shippingLabel',
            'categoryShippingBreakdown',
            'user',
            'refundPolicy',
            'couponDiscount',
            'appliedCouponCode',
            'couponError'
        ));
    }

    public function process(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:paypal,stripe',
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_country' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'accepted_refund_policy' => 'required|accepted',
            'coupon_code' => 'nullable|string|max:64',
        ]);

        $method = $request->input('payment_method');
        if ($method === 'stripe' && ! $this->adminGatewayEnabled('stripe')) {
            return back()->with('error', 'Stripe is not enabled.');
        }
        if ($method === 'paypal' && ! $this->adminGatewayEnabled('paypal')) {
            return back()->with('error', 'PayPal is not enabled.');
        }

        $user = Auth::user();
        $cartItems = Cart::where('user_id', $user->id)
            ->with(['product.category', 'product.primaryImage', 'product.services', 'product.optionGroups.options'])
            ->get();

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Your cart is empty.');
        }

        $subtotal = round($cartItems->sum(fn (Cart $item) => $item->quantity * $item->lineUnitPrice()), 2);
        $couponCode = strtoupper(trim((string) $request->input('coupon_code', '')));
        $couponId = null;
        $couponDiscount = 0.0;

        if ($couponCode !== '') {
            $categoryIds = $cartItems
                ->map(fn ($item) => $item->product?->category_id)
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $baseShipping = ShopCartController::resolveBaseShippingForCart($cartItems);
            $couponResult = $this->couponService->preview(
                $couponCode,
                $subtotal,
                0,
                (int) $user->id,
                $categoryIds,
                'products',
                [],
                $baseShipping
            );

            if (! ($couponResult['ok'] ?? false)) {
                return back()->withInput()->with('error', (string) ($couponResult['message'] ?? 'Invalid coupon code.'));
            }

            $couponId = (int) ($couponResult['coupon_id'] ?? 0);
            $couponCode = (string) ($couponResult['code'] ?? $couponCode);
            $couponDiscount = round((float) ($couponResult['coupon_discount'] ?? 0), 2);
            $orderSummary = is_array($couponResult['order_summary'] ?? null)
                ? ShopCartController::mergeCategoryShippingIntoSummary($couponResult['order_summary'], $cartItems)
                : ShopCartController::buildOrderSummary($subtotal, 0, $cartItems);
        } else {
            $orderSummary = ShopCartController::buildOrderSummary($subtotal, 0, $cartItems);
        }

        $tax = $orderSummary['tax'];
        $shipping = $orderSummary['shipping'];
        $total = $orderSummary['total'];
        $taxPercent = $orderSummary['tax_percent'];

        foreach ($cartItems as $cartItem) {
            if ($cartItem->product->stock < $cartItem->quantity) {
                return back()->with('error', "Insufficient stock for {$cartItem->product->name}.");
            }
        }

        $currency = config('shop.currency', ShopCartController::CURRENCY);

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'coupon_id' => $couponId ?: null,
                'coupon_code' => $couponCode !== '' ? $couponCode : null,
                'coupon_discount_amount' => $couponDiscount,
                'total_amount' => $total,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'tax_percent' => $taxPercent,
                'shipping_amount' => $shipping,
                'payment_status' => 'pending',
                'payment_method' => $method,
                'order_status' => 'pending',
            ]);

            foreach ($cartItems as $cartItem) {
                $lineUnit = $cartItem->lineUnitPrice();
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $lineUnit,
                    'subtotal' => $cartItem->quantity * $lineUnit,
                ]);

                $cartItem->product->decrement('stock', $cartItem->quantity);
            }

            app(\App\Services\Vendor\VendorOrderSyncService::class)->syncFromOrder(
                $order->fresh('items.product')
            );

            if ($method === 'paypal') {
                $paypalResponse = $this->paypal->createOrder(
                    (float) $total,
                    $currency,
                    route('client.checkout.success', ['order_id' => $order->id]),
                    route('client.checkout.cancel', ['order_id' => $order->id]),
                    (string) $order->id
                );

                $approvalUrl = $paypalResponse['approval_url'] ?? null;
                $paypalId = $paypalResponse['id'] ?? null;

                if ($paypalId && $approvalUrl) {
                    $order->update(['payment_reference' => $paypalId]);
                    DB::commit();

                    return redirect()->away($approvalUrl);
                }

                throw new \Exception('PayPal order creation failed');
            }

            $successUrl = route('client.checkout.success', ['order_id' => $order->id]).'?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = route('client.checkout.cancel', ['order_id' => $order->id]);

            $stripe = $this->stripeCheckout->createForOrder($order, $successUrl, $cancelUrl, $currency);
            if (isset($stripe['error'])) {
                throw new \Exception($stripe['error']);
            }
            $checkoutUrl = $stripe['url'] ?? null;
            if (! $checkoutUrl) {
                throw new \Exception('Stripe did not return a checkout URL.');
            }
            $order->update(['payment_reference' => $stripe['session_id'] ?? null]);
            DB::commit();

            return redirect()->away($checkoutUrl);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::warning('Client checkout failed', ['message' => $e->getMessage()]);

            return back()->with('error', 'Order processing failed: '.$e->getMessage());
        }
    }

    public function success(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();

        if ($order->user_id !== $user->id) {
            abort(403);
        }

        if ($request->has('token') && $request->has('PayerID')) {
            $capture = $this->paypal->captureOrder($request->input('token'));
            if (isset($capture['error'])) {
                return redirect()->route('client.orders.index')
                    ->with('error', 'PayPal capture failed. Contact support if you were charged.');
            }
            $status = $capture['status'] ?? '';
            if ($status === 'COMPLETED' || ! empty($capture['placeholder'])) {
                if ($order->payment_status !== 'paid') {
                    $order->update([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ]);
                    $this->notifyAdminsShopOrder($order, 'PayPal (web)');
                }
                Cart::where('user_id', $user->id)->delete();

                return redirect()->route('client.orders.index')->with('success', 'Payment successful! Your order has been placed.');
            }

            return redirect()->route('client.orders.index')
                ->with('error', 'PayPal payment was not completed. You can try again from your orders or contact support.');
        }

        if ($request->filled('session_id')) {
            $order->refresh();
            if ($order->payment_status === 'paid') {
                Cart::where('user_id', $user->id)->delete();

                return redirect()->route('client.orders.index')->with('success', 'Payment successful! Your order has been placed.');
            }

            return redirect()->route('client.orders.index')
                ->with('info', 'Thank you. Your payment is being confirmed; refresh orders in a moment if status is still pending.');
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

        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        $order->delete();

        return redirect()->route('client.cart.index')->with('error', 'Payment was cancelled. Your order has been cancelled.');
    }

    protected function adminGatewayEnabled(string $gateway): bool
    {
        if ($gateway === 'stripe') {
            return StripeCredentials::isStripeUsableForCheckout();
        }

        $v = Setting::get("{$gateway}_enabled", false);

        return filter_var($v, FILTER_VALIDATE_BOOLEAN)
            || $v === '1'
            || $v === 1
            || $v === true;
    }

    protected function notifyAdminsShopOrder(Order $order, string $via): void
    {
        OrderPaidSideEffects::run($order, $via);
    }
}
