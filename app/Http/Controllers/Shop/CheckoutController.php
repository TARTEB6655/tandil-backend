<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
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

        // Checkout.com: one gateway for card, Apple Pay, Samsung Pay, Tabby, Tamara
        $methods = [
            ['id' => 'card', 'type' => 'card', 'label' => 'Visa / Credit Card'],
            ['id' => 'apple_pay', 'type' => 'apple_pay', 'label' => 'Apple Pay'],
            ['id' => 'samsung_pay', 'type' => 'samsung_pay', 'label' => 'Samsung Pay'],
            ['id' => 'tabby', 'type' => 'tabby', 'label' => 'Tabby'],
            ['id' => 'tamara', 'type' => 'tamara', 'label' => 'Tamara'],
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

        $preview = CartController::checkoutPreview($request, $user->id);
        $items = $preview['items']->map(fn ($item) => CartController::cartItemToFrontend($item))->values()->all();
        $orderSummary = CartController::buildOrderSummary($preview['subtotal'], 0);

        $addresses = UserAddress::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => $a->toApiArray())
            ->values()
            ->all();

        $paymentMethods = [
            ['id' => 'card', 'type' => 'card', 'label' => 'Visa / Credit Card'],
            ['id' => 'apple_pay', 'type' => 'apple_pay', 'label' => 'Apple Pay'],
            ['id' => 'samsung_pay', 'type' => 'samsung_pay', 'label' => 'Samsung Pay'],
            ['id' => 'tabby', 'type' => 'tabby', 'label' => 'Tabby'],
            ['id' => 'tamara', 'type' => 'tamara', 'label' => 'Tamara'],
        ];

        return ApiResponse::success('Checkout review retrieved.', [
            'items' => $items,
            'order_summary' => $orderSummary,
            'addresses' => $addresses,
            'payment_methods' => $paymentMethods,
        ]);
    }
}
