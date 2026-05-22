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
     * Promo codes for a category or service catalog screen (client dashboard).
     */
    public function browse(Request $request, ShopCouponService $coupons)
    {
        $request->validate([
            'category_id' => 'sometimes|integer|exists:categories,id',
            'service_id' => 'sometimes|integer|exists:services,id',
        ]);

        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $serviceId = $request->filled('service_id') ? (int) $request->input('service_id') : null;

        if ($categoryId === null && $serviceId === null) {
            return ApiResponse::error('Provide category_id or service_id to list applicable offers.', 422);
        }

        $result = $coupons->listForBrowse($categoryId, $serviceId);

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

        $subtotal = $request->filled('subtotal')
            ? (float) $request->input('subtotal')
            : (float) $preview['subtotal'];

        $catalogDiscount = $request->filled('catalog_discount')
            ? (float) $request->input('catalog_discount')
            : (float) ($preview['catalog_discount'] ?? 0);

        $cartCategoryIds = $request->input('cart_category_ids', $preview['cart_category_ids'] ?? []);
        if (is_string($cartCategoryIds)) {
            $decoded = json_decode($cartCategoryIds, true);
            $cartCategoryIds = is_array($decoded) ? $decoded : [];
        }

        $cartCatalog = $request->input('cart_catalog', $preview['cart_catalog'] ?? 'both');
        $cartServiceIds = $request->input('cart_service_ids', $preview['cart_service_ids'] ?? []);
        if (is_string($cartServiceIds)) {
            $decoded = json_decode($cartServiceIds, true);
            $cartServiceIds = is_array($decoded) ? $decoded : [];
        }

        $lists = $coupons->listForCheckout(
            $subtotal,
            $catalogDiscount,
            (int) $user->id,
            array_map('intval', (array) $cartCategoryIds),
            (string) $cartCatalog,
            array_map('intval', (array) $cartServiceIds)
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

        $subtotal = $request->filled('subtotal')
            ? (float) $request->input('subtotal')
            : (float) $preview['subtotal'];

        $catalogDiscount = $request->filled('catalog_discount')
            ? (float) $request->input('catalog_discount')
            : (float) ($preview['catalog_discount'] ?? 0);

        $cartCategoryIds = $request->input('cart_category_ids', $preview['cart_category_ids'] ?? []);
        if (is_string($cartCategoryIds)) {
            $decoded = json_decode($cartCategoryIds, true);
            $cartCategoryIds = is_array($decoded) ? $decoded : [];
        }

        $cartCatalog = $request->input('cart_catalog', $preview['cart_catalog'] ?? 'both');
        $cartServiceIds = $request->input('cart_service_ids', $preview['cart_service_ids'] ?? []);
        if (is_string($cartServiceIds)) {
            $decoded = json_decode($cartServiceIds, true);
            $cartServiceIds = is_array($decoded) ? $decoded : [];
        }

        $result = $coupons->preview(
            (string) $request->input('code'),
            $subtotal,
            $catalogDiscount,
            (int) $user->id,
            array_map('intval', (array) $cartCategoryIds),
            (string) $cartCatalog,
            array_map('intval', (array) $cartServiceIds)
        );

        if (! ($result['ok'] ?? false)) {
            return ApiResponse::error($result['message'] ?? 'Invalid coupon.', 422);
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
            return ApiResponse::error($pack['error'], 422);
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

        if ($request->filled('payment_intent_id')) {
            $payment = $stripeCheckout->updatePaymentIntentForAppliedCoupon(
                $request,
                $user,
                $pack,
                $orderSummary
            );
            if (! ($payment['ok'] ?? false)) {
                return ApiResponse::error($payment['message'] ?? 'Could not update payment.', $payment['status'] ?? 422);
            }
            $data['payment'] = $payment['data'] ?? null;
        }

        return ApiResponse::success('Coupon applied.', $data);
    }
}
