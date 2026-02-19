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

        // Options for Place Order: send payment_method as cod | paypal | paypal_login
        $methods = [
            ['id' => 'paypal', 'type' => 'paypal', 'label' => 'PayPal (redirect to approve)'],
            ['id' => 'paypal_login', 'type' => 'paypal_login', 'label' => 'Login to PayPal'],
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
        $orderSummary = CartController::buildOrderSummary($subtotal, 0);

        $addresses = UserAddress::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => $a->toApiArray())
            ->values()
            ->all();

        $paymentMethods = [
            ['id' => 'paypal', 'type' => 'paypal', 'label' => 'PayPal (redirect to approve)'],
            ['id' => 'paypal_login', 'type' => 'paypal_login', 'label' => 'Login to PayPal'],
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
