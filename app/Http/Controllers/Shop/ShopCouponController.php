<?php

namespace App\Http\Controllers\Shop;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ShopCouponService;
use App\Services\ShopStripeMobilePaymentService;
use Illuminate\Http\Request;

class ShopCouponController extends Controller
{
    /**
     * GET /api/shop/coupons/browse
     * Promo codes for store-wide (all products), category PLP, or service PLP.
     */
    public function browse(Request $request, ShopCouponService $coupons)
    {
        $request->validate([
            'all' => 'sometimes|boolean',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'service_id' => 'sometimes|integer|exists:services,id',
        ]);

        $storewideAll = $request->boolean('all');
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $serviceId = $request->filled('service_id') ? (int) $request->input('service_id') : null;

        $modes = ($storewideAll ? 1 : 0) + ($categoryId !== null ? 1 : 0) + ($serviceId !== null ? 1 : 0);
        if ($modes !== 1) {
            return ApiResponse::error('Send exactly one of: all=1 (all products), category_id, or service_id.', 422);
        }

        if ($categoryId !== null && $serviceId !== null) {
            return ApiResponse::error('Send either category_id or service_id, not both.', 422);
        }

        $result = $coupons->listForBrowse($categoryId, $serviceId, $storewideAll);

        return response()->json([
            'success' => true,
            'message' => 'Coupons loaded.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * POST /api/shop/coupons/checkout-offers
     * "Choose a promo code" modal: available vs not eligible for the current cart.
     */
    public function checkoutOffers(Request $request, ShopCouponService $coupons)
    {
        $request->validate([
            'subtotal' => 'sometimes|numeric|min:0',
            'catalog_discount' => 'sometimes|numeric|min:0',
            'cart_category_ids' => 'sometimes|array',
            'cart_category_ids.*' => 'integer',
            'cart_catalog' => 'sometimes|string|in:products,services,both',
            'cart_service_ids' => 'sometimes|array',
            'cart_service_ids.*' => 'integer',
            'product_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
        ]);

        $user = $request->user();
        $preview = CartController::checkoutPreview($request, $user->id);
        $amounts = CartController::resolveCheckoutAmountsFromRequest($request, $preview);

        $lists = $coupons->listForCheckout(
            $amounts['subtotal'],
            $amounts['catalog_discount'],
            (int) $user->id,
            $amounts['cart_category_ids'],
            $amounts['cart_catalog'],
            $amounts['cart_service_ids']
        );

        return ApiResponse::success('Checkout offers loaded.', $lists);
    }

    /**
     * POST /api/shop/coupons/validate
     */
    public function validateCode(Request $request, ShopCouponService $coupons)
    {
        $request->validate([
            'code' => 'required|string|max:64',
            'subtotal' => 'sometimes|numeric|min:0',
            'catalog_discount' => 'sometimes|numeric|min:0',
            'cart_category_ids' => 'sometimes|array',
            'cart_category_ids.*' => 'integer',
            'cart_catalog' => 'sometimes|string|in:products,services,both',
            'cart_service_ids' => 'sometimes|array',
            'cart_service_ids.*' => 'integer',
            'product_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
        ]);

        $user = $request->user();
        $preview = CartController::checkoutPreview($request, $user->id);
        $amounts = CartController::resolveCheckoutAmountsFromRequest($request, $preview);

        if ($amounts['subtotal'] < 0.01 && ! $request->filled('product_id')) {
            return ApiResponse::error('Your cart is empty. Add items before applying a coupon.', 422, [
                'subtotal' => 0.0,
            ]);
        }

        $result = $coupons->preview(
            (string) $request->input('code'),
            $amounts['subtotal'],
            $amounts['catalog_discount'],
            (int) $user->id,
            $amounts['cart_category_ids'],
            $amounts['cart_catalog'],
            $amounts['cart_service_ids']
        );

        if (! ($result['ok'] ?? false)) {
            return ApiResponse::error(
                $result['message'] ?? 'Invalid coupon.',
                422,
                is_array($result['error_details'] ?? null) ? $result['error_details'] : []
            );
        }

        $orderSummary = $result['order_summary'] ?? [];
        if (is_array($orderSummary)) {
            CartController::addCheckoutUiAliases($orderSummary);
        }

        return ApiResponse::success($result['message'] ?? 'Coupon applied.', [
            'coupon_id' => $result['coupon_id'],
            'code' => $result['code'],
            'discount_type' => $result['discount_type'],
            'coupon_discount' => (float) ($result['coupon_discount'] ?? 0),
            'free_shipping' => (bool) ($result['free_shipping'] ?? false),
            'order_summary' => $orderSummary,
        ]);
    }

    /**
     * POST /api/shop/coupons/apply
     * Checkout "Apply" button: recalculate payment summary (subtotal, VAT, total) and optionally update an existing Stripe PaymentIntent.
     */
    public function applyCode(Request $request, ShopStripeMobilePaymentService $stripeCheckout)
    {
        $request->validate([
            'code' => 'required_without:coupon_code|string|max:64',
            'coupon_code' => 'required_without:code|string|max:64',
            'subtotal' => 'sometimes|numeric|min:0',
            'catalog_discount' => 'sometimes|numeric|min:0',
            'cart_category_ids' => 'sometimes|array',
            'cart_category_ids.*' => 'integer',
            'cart_catalog' => 'sometimes|string|in:products,services,both',
            'cart_service_ids' => 'sometimes|array',
            'cart_service_ids.*' => 'integer',
            'product_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
            'payment_intent_id' => 'sometimes|string|max:255',
            'paymentIntentId' => 'sometimes|string|max:255',
            'use_wallet' => 'sometimes|boolean',
            'wallet_amount' => 'sometimes|numeric|min:0',
        ]);

        if (! $request->filled('payment_intent_id') && $request->filled('paymentIntentId')) {
            $request->merge(['payment_intent_id' => $request->input('paymentIntentId')]);
        }

        $code = strtoupper(trim((string) $request->input('code', $request->input('coupon_code', ''))));
        $request->merge(['coupon_code' => $code]);

        $user = $request->user();
        $pack = CartController::checkoutTotalsForRequest($request, $user);
        if ($pack['error'] !== null) {
            return ApiResponse::error(
                $pack['error'],
                422,
                is_array($pack['error_details'] ?? null) ? $pack['error_details'] : []
            );
        }
        $orderSummary = $pack['order_summary'];
        $orderSummary = CartController::mergeWalletPreviewIntoOrderSummary($orderSummary, $request, $user);
        CartController::addCheckoutUiAliases($orderSummary);

        $couponMeta = is_array($orderSummary['coupon'] ?? null) ? $orderSummary['coupon'] : [];

        $data = [
            'coupon_id' => $pack['coupon_id'],
            'code' => $pack['coupon_code'],
            'discount_type' => $couponMeta['discount_type'] ?? null,
            'discount_value' => isset($couponMeta['discount_value']) ? (float) $couponMeta['discount_value'] : null,
            'discount_label' => $couponMeta['discount_label'] ?? null,
            'coupon_discount' => (float) $pack['coupon_merchandise_discount'],
            'free_shipping' => $pack['coupon_shipping_discount'] > 0,
            'order_summary' => $orderSummary,
            'payment' => null,
        ];

        if (! $request->filled('payment_intent_id')) {
            $pendingPi = $stripeCheckout->findActivePaymentIntentIdForUser((int) $user->id);
            if ($pendingPi !== null) {
                $request->merge(['payment_intent_id' => $pendingPi]);
            }
        }

        if ($request->filled('payment_intent_id')) {
            $payment = $stripeCheckout->syncPaymentIntentAfterCoupon(
                $request,
                $user,
                $pack,
                $orderSummary
            );
            if (! ($payment['ok'] ?? false)) {
                return ApiResponse::error($payment['message'] ?? 'Could not update payment.', $payment['status'] ?? 422);
            }
            $data['payment'] = $payment['data'] ?? null;
            if (is_array($data['payment'])) {
                $data['stripe_amount_minor'] = $data['payment']['amount_minor'] ?? null;
                $data['reinitialize_payment_sheet'] = true;
            }
        }

        return ApiResponse::success('Coupon applied.', $data);
    }
}
