<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\UserAddress;
use Illuminate\Http\Request;

/**
 * Checkout flow: Address → Payment → Review.
 * Cart APIs (add, view, update, remove) are in CartController.
 */
class CheckoutController extends Controller
{
    /**
     * GET /api/auth/shop/checkout/payment-methods
     * List payment options for Checkout – Payment step (Visa ****4242, PayPal, Cash on Delivery, etc.).
     * Frontend uses these to show radio options; selected id/type is sent when placing order.
     */
    public function paymentMethods(Request $request)
    {
        $user = $request->user();

        // Static list for now; can be replaced with saved payment methods from DB/Stripe later.
        $methods = [
            ['id' => 'card_4242', 'type' => 'card', 'label' => 'Visa ending in 4242', 'last4' => '4242'],
            ['id' => 'card_8888', 'type' => 'card', 'label' => 'Mastercard ending in 8888', 'last4' => '8888'],
            ['id' => 'paypal', 'type' => 'paypal', 'label' => 'PayPal'],
            ['id' => 'cod', 'type' => 'cod', 'label' => 'Cash on Delivery'],
        ];

        return ApiResponse::success('Payment methods retrieved.', $methods);
    }

    /**
     * GET /api/auth/shop/checkout/review
     * Review step: cart items + order summary + user addresses + payment methods in one response.
     * Frontend can pre-fill Review screen and let user select address/payment if not already chosen.
     */
    public function review(Request $request)
    {
        $user = $request->user();

        $cartItems = Cart::where('user_id', $user->id)
            ->with(['product.category', 'product.primaryImage'])
            ->get();

        $validItems = $cartItems->filter(fn ($item) => $item->product !== null);
        $items = $validItems->map(fn ($item) => CartController::cartItemToFrontend($item))->values()->all();

        $subtotal = $validItems->sum(fn ($item) => $item->quantity * (float) $item->product->price);
        $subtotal = round($subtotal, 2);
        $shipping = (float) config('shop.shipping_amount', 9.99);
        $discount = 0;
        $total = round($subtotal - $discount + $shipping, 2);

        $orderSummary = [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $total,
            'currency' => CartController::CURRENCY,
        ];

        $addresses = UserAddress::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => $a->toApiArray())
            ->values()
            ->all();

        $paymentMethods = [
            ['id' => 'card_4242', 'type' => 'card', 'label' => 'Visa ending in 4242', 'last4' => '4242'],
            ['id' => 'card_8888', 'type' => 'card', 'label' => 'Mastercard ending in 8888', 'last4' => '8888'],
            ['id' => 'paypal', 'type' => 'paypal', 'label' => 'PayPal'],
            ['id' => 'cod', 'type' => 'cod', 'label' => 'Cash on Delivery'],
        ];

        return ApiResponse::success('Checkout review retrieved.', [
            'items' => $items,
            'order_summary' => $orderSummary,
            'addresses' => $addresses,
            'payment_methods' => $paymentMethods,
        ]);
    }
}
