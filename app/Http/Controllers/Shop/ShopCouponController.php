<?php

namespace App\Http\Controllers\Shop;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ShopCouponService;
use Illuminate\Http\Request;

class ShopCouponController extends Controller
{
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

        $result = $coupons->preview(
            (string) $request->input('code'),
            $subtotal,
            $catalogDiscount,
            (int) $user->id,
            array_map('intval', (array) $cartCategoryIds),
            (string) $cartCatalog
        );

        if (! ($result['ok'] ?? false)) {
            return ApiResponse::error($result['message'] ?? 'Invalid coupon.', 422);
        }

        return ApiResponse::success($result['message'] ?? 'Coupon applied.', [
            'coupon_id' => $result['coupon_id'],
            'code' => $result['code'],
            'discount_type' => $result['discount_type'],
            'coupon_discount' => (float) ($result['coupon_discount'] ?? 0),
            'free_shipping' => (bool) ($result['free_shipping'] ?? false),
            'order_summary' => $result['order_summary'] ?? null,
        ]);
    }
}
