<?php

namespace App\Http\Controllers\Shop;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ShopStripeMobilePaymentService;
use Illuminate\Http\Request;

/**
 * Mobile app (React Native) Stripe Payment Sheet: PaymentIntent + confirm order.
 *
 * POST /api/shop/checkout/stripe/payment-intent
 * POST /api/shop/checkout/confirm
 */
class ShopStripeMobileCheckoutController extends Controller
{
    public function __construct(
        protected ShopStripeMobilePaymentService $mobileCheckout
    ) {}

    public function paymentIntent(Request $request)
    {
        $result = $this->mobileCheckout->createPaymentIntent($request, $request->user());
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::error($result['message'], $result['status'] ?? 400);
        }

        return ApiResponse::success($result['message'], $result['data']);
    }

    public function confirm(Request $request)
    {
        if (! $request->filled('payment_intent_id') && $request->filled('paymentIntentId')) {
            $request->merge(['payment_intent_id' => $request->input('paymentIntentId')]);
        }

        $request->validate([
            'payment_intent_id' => 'required|string|max:255',
        ]);

        $result = $this->mobileCheckout->confirmOrder($request->user(), (string) $request->input('payment_intent_id'));
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::error($result['message'], $result['status'] ?? 400);
        }

        $code = (int) ($result['http_code'] ?? 201);

        return ApiResponse::success($result['message'], $result['data'], $code);
    }
}
