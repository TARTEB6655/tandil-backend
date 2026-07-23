<?php

namespace App\Http\Controllers\Api\Client;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\WalletTopUpStripeService;
use Illuminate\Http\Request;

/**
 * Client "Add Money" / wallet top-up APIs.
 * Separate from /api/shop/checkout — never creates product/service orders.
 */
class WalletTopUpController extends Controller
{
    public function __construct(private WalletTopUpStripeService $topUps)
    {
    }

    /**
     * GET /api/client/wallet/add-money
     * Add Money screen: balance, presets (50/100/150/200), limits, payment methods.
     */
    public function options(Request $request)
    {
        $result = $this->topUps->options($request->user());

        return ApiResponse::success('Wallet add-money options retrieved successfully.', $result['data']);
    }

    /**
     * POST /api/client/wallet/add-money/payment-intent
     * Body: amount (required), payment_method (optional: stripe|apple_pay).
     * Returns client_secret + publishable_key for Stripe Payment Sheet (card / Apple Pay).
     */
    public function paymentIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|in:stripe,apple_pay',
        ]);

        $result = $this->topUps->createPaymentIntent($request, $request->user());
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success('Wallet add-money payment intent created successfully.', $result['data']);
    }

    /**
     * POST /api/client/wallet/add-money/confirm
     * Body: payment_intent_id. Credits wallet after Payment Sheet success (idempotent with webhook).
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $result = $this->topUps->confirm($request, $request->user());
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success('Money added to wallet successfully.', $result['data']);
    }
}
