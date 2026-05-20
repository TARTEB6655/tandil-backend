<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Coupon;
use App\Models\Order;
use Carbon\Carbon;

/**
 * Validates coupons against cart subtotal and usage limits; computes merchandise + shipping discounts.
 */
final class ShopCouponService
{
    /**
     * @return array{
     *   ok: bool,
     *   message?: string,
     *   coupon?: array<string, mixed>,
     *   merchandise_discount?: float,
     *   shipping_discount?: float,
     *   order_summary?: array<string, mixed>
     * }
     */
    public function preview(?string $code, float $subtotal, ?int $userId = null): array
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return ['ok' => false, 'message' => 'Please enter a coupon code.'];
        }

        $coupon = Coupon::query()->where('code', $code)->first();
        if (! $coupon) {
            return ['ok' => false, 'message' => 'Invalid coupon code.'];
        }

        $err = $this->validateEligibility($coupon, $subtotal, $userId);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        [$merch, $ship] = $this->computeDiscounts($coupon, $subtotal);
        $summary = CartController::buildOrderSummaryWithAdjustments($subtotal, $merch, $ship);

        return [
            'ok' => true,
            'message' => 'Coupon applied.',
            'coupon' => $this->couponToApi($coupon),
            'merchandise_discount' => $merch,
            'shipping_discount' => $ship,
            'order_summary' => $summary,
        ];
    }

    /**
     * @return array{0: float, 1: float} [merchandise_discount, shipping_discount]
     */
    public function computeDiscounts(Coupon $coupon, float $subtotal): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $baseShipping = CartController::getEffectiveShippingAmount();

        $merch = 0.0;
        $ship = 0.0;

        $type = strtolower((string) $coupon->discount_type);

        if ($type === 'percentage') {
            $pct = (float) ($coupon->discount_value ?? 0);
            $merch = round($subtotal * ($pct / 100), 2);
            $max = $coupon->max_discount_amount;
            if ($max !== null && (float) $max > 0) {
                $merch = min($merch, (float) $max);
            }
            $merch = min($merch, $subtotal);
        } elseif ($type === 'fixed_amount') {
            $merch = min((float) ($coupon->discount_value ?? 0), $subtotal);
        } elseif ($type === 'free_shipping') {
            $ship = round($baseShipping, 2);
        }

        return [round($merch, 2), round($ship, 2)];
    }

    public function validateEligibility(Coupon $coupon, float $subtotal, ?int $userId): ?string
    {
        if (! $coupon->is_active) {
            return 'This coupon is not active.';
        }

        $today = Carbon::today();
        if ($coupon->starts_at && $today->lt($coupon->starts_at->startOfDay())) {
            return 'This coupon is not valid yet.';
        }
        if ($coupon->ends_at && $today->gt($coupon->ends_at->endOfDay())) {
            return 'This coupon has expired.';
        }

        $min = (float) ($coupon->min_order_amount ?? 0);
        if ($subtotal + 0.0001 < $min) {
            return 'Order subtotal does not meet the minimum for this coupon (AED '.number_format($min, 2).').';
        }

        if ($coupon->usage_limit !== null && $coupon->usage_limit > 0) {
            $used = $coupon->paidOrdersCount();
            if ($used >= $coupon->usage_limit) {
                return 'This coupon has reached its maximum number of uses.';
            }
        }

        if ($userId !== null && $coupon->usage_limit_per_user !== null && $coupon->usage_limit_per_user > 0) {
            $userUses = $coupon->paidOrdersCountForUser($userId);
            if ($userUses >= $coupon->usage_limit_per_user) {
                return 'You have already used this coupon the maximum number of times.';
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function couponToApi(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'title' => $coupon->title,
            'description' => $coupon->description,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value !== null ? (float) $coupon->discount_value : null,
            'min_order_amount' => (float) ($coupon->min_order_amount ?? 0),
            'max_discount_amount' => $coupon->max_discount_amount !== null ? (float) $coupon->max_discount_amount : null,
        ];
    }
}
