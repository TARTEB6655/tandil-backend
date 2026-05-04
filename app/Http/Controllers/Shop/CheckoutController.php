<?php

namespace App\Http\Controllers\Shop;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\UserAddress;
use App\Support\RefundPolicy;
use App\Support\StripeCredentials;
use Illuminate\Http\Request;

/**
 * Checkout flow: Address → Payment → Review.
 * Cart APIs (add, view, update, remove) are in CartController.
 */
class CheckoutController extends Controller
{
    /**
     * GET /api/shop/payment-gateways (public)
     * Which shop gateways are enabled (Stripe / PayPal). No secrets.
     */
    public function publicPaymentGateways()
    {
        return ApiResponse::success('Payment gateways retrieved.', $this->stripePayPalMethods());
    }

    /**
     * GET /api/shop/checkout/payment-methods (auth)
     * Stripe and PayPal only; `enabled` reflects admin payment settings.
     */
    public function paymentMethods(Request $request)
    {
        return ApiResponse::success('Payment methods retrieved.', $this->stripePayPalMethods());
    }

    /**
     * GET /api/shop/refund-policy
     */
    public function refundPolicy()
    {
        return ApiResponse::success('Refund policy retrieved.', RefundPolicy::policyForApi());
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

        return ApiResponse::success('Checkout review retrieved.', [
            'items' => $items,
            'order_summary' => $orderSummary,
            'addresses' => $addresses,
            'payment_methods' => $this->stripePayPalMethods(),
            'refund_policy' => RefundPolicy::policyForApi(),
        ]);
    }

    /**
     * @return list<array{id: string, type: string, label: string, name: string, enabled: bool}>
     */
    protected function stripePayPalMethods(): array
    {
        return [
            [
                'id' => 'stripe',
                'type' => 'stripe',
                'label' => 'Stripe',
                'name' => 'Stripe',
                'enabled' => StripeCredentials::isStripeUsableForCheckout(),
            ],
            [
                'id' => 'paypal',
                'type' => 'paypal',
                'label' => 'PayPal',
                'name' => 'PayPal',
                'enabled' => $this->settingEnabled('paypal_enabled'),
            ],
        ];
    }

    protected function settingEnabled(string $key): bool
    {
        $v = Setting::get($key, false);

        return filter_var($v, FILTER_VALIDATE_BOOLEAN)
            || $v === '1'
            || $v === 1
            || $v === true;
    }
}
