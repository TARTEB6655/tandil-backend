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
     * Body: { "code": "SAVE10" } — optional query/body product_id + quantity for Buy Now preview (same as order-summary).
     */
    public function validateCode(Request $request, ShopCouponService $coupons)
    {
        $request->validate([
            'code' => 'required|string|max:64',
            'product_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
        ]);

        $user = $request->user();
        $preview = CartController::checkoutPreview($request, $user->id);
        $subtotal = (float) $preview['subtotal'];

        $result = $coupons->preview((string) $request->input('code'), $subtotal, (int) $user->id);
        if (! ($result['ok'] ?? false)) {
            return ApiResponse::error($result['message'] ?? 'Invalid coupon.', 422);
        }

        return ApiResponse::success($result['message'] ?? 'Coupon applied.', [
            'coupon' => $result['coupon'],
            'merchandise_discount' => (float) ($result['merchandise_discount'] ?? 0),
            'shipping_discount' => (float) ($result['shipping_discount'] ?? 0),
            'order_summary' => $result['order_summary'],
        ]);
    }
}
