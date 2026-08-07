<?php

namespace App\Http\Controllers\Api\Client;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionPaymentStripeService;
use Illuminate\Http\Request;

/**
 * Client "Membership checkout" — renew or upgrade a subscription via Stripe
 * Payment Sheet (card + Apple Pay). Separate from admin subscription
 * management (Subscription\SubscriptionController) and wallet top-up.
 */
class MembershipPaymentController extends Controller
{
    public function __construct(private SubscriptionPaymentStripeService $payments)
    {
    }

    /**
     * GET /api/client/memberships/screen
     * One call for the whole Membership screen: active_membership (current plan
     * summary + renew_price + upgrade_plans, or null if none active) + memberships
     * (full "Your memberships" list). Avoids calling List + Get Upgrade Options separately.
     */
    public function screen(Request $request)
    {
        $result = $this->payments->membershipScreen($request->user());

        return ApiResponse::success('Membership screen data retrieved successfully.', $result['data']);
    }

    /**
     * GET /api/client/memberships/{id}/upgrade-options
     * Membership screen: current plan + eligible upgrade plans + payment methods.
     */
    public function upgradeOptions(Request $request, int $id)
    {
        $subscription = Subscription::findOrFail($id);

        $result = $this->payments->upgradeOptions($request->user(), $subscription);
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success('Upgrade options retrieved successfully.', $result['data']);
    }

    /**
     * POST /api/client/memberships/{id}/renew/payment-intent
     * Body: payment_method (optional: stripe|apple_pay).
     */
    public function renewPaymentIntent(Request $request, int $id)
    {
        $request->validate([
            'payment_method' => 'nullable|string|in:stripe,apple_pay',
        ]);

        $subscription = Subscription::findOrFail($id);

        $result = $this->payments->createPaymentIntent($request, $request->user(), $subscription, 'renew');
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success('Renewal payment intent created successfully.', $result['data']);
    }

    /**
     * POST /api/client/memberships/{id}/upgrade/payment-intent
     * Body: target_plan (required: 1_month|3_month|6_month|12_month), payment_method (optional).
     */
    public function upgradePaymentIntent(Request $request, int $id)
    {
        $request->validate([
            'target_plan' => 'required|string|in:1_month,3_month,6_month,12_month',
            'payment_method' => 'nullable|string|in:stripe,apple_pay',
        ]);

        $subscription = Subscription::findOrFail($id);

        $result = $this->payments->createPaymentIntent($request, $request->user(), $subscription, 'upgrade');
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success('Upgrade payment intent created successfully.', $result['data']);
    }

    /**
     * POST /api/client/memberships/payment/confirm
     * Body: payment_intent_id. Applies the renew/upgrade after Payment Sheet success
     * (idempotent with the Stripe webhook).
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $result = $this->payments->confirm($request, $request->user());
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['code']);
        }

        return ApiResponse::success('Membership payment confirmed successfully.', $result['data']);
    }
}
