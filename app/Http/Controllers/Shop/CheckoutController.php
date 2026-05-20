<?php

namespace App\Http\Controllers\Shop;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\UserPaymentMethod;
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
        $methods = $this->stripePayPalMethods();
        $savedPayPal = UserPaymentMethod::query()
            ->where('user_id', $request->user()->id)
            ->where('gateway', 'paypal')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get(['id', 'label', 'email', 'is_default'])
            ->values()
            ->all();

        return ApiResponse::success('Payment methods retrieved.', [
            'methods' => $methods,
            'saved_paypal_methods' => $savedPayPal,
        ]);
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

        $request->validate([
            'use_wallet' => 'sometimes|boolean',
            'wallet_amount' => 'sometimes|numeric|min:0',
            'coupon_code' => 'sometimes|string|max:64',
        ]);

        $pack = CartController::checkoutTotalsForRequest($request, $user);
        if ($pack['error'] !== null) {
            return ApiResponse::error($pack['error'], 422);
        }

        $cartPreview = $pack['cart_preview'];
        $items = $cartPreview['items']->map(fn ($item) => CartController::cartItemToFrontend($item))->values()->all();
        $orderSummary = CartController::mergeWalletPreviewIntoOrderSummary($pack['order_summary'], $request, $user);

        $addresses = UserAddress::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => $a->toApiArray())
            ->values()
            ->all();

        $walletBalance = round((float) ($user->wallet_balance ?? 0), 2);
        $payload = [
            'items' => $items,
            'order_summary' => $orderSummary,
            'addresses' => $addresses,
            'payment_methods' => $this->stripePayPalMethods(),
            'saved_paypal_methods' => UserPaymentMethod::query()
                ->where('user_id', $user->id)
                ->where('gateway', 'paypal')
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get(['id', 'label', 'email', 'is_default'])
                ->values()
                ->all(),
            'refund_policy' => RefundPolicy::policyForApi(),
        ];

        if ($walletBalance > 0) {
            $payload['wallet_available'] = true;
            $payload['wallet_balance'] = $walletBalance;
        } else {
            $payload['wallet_available'] = false;
        }

        return ApiResponse::success('Checkout review retrieved.', $payload);
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
