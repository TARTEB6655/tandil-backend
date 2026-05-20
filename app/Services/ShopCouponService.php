<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Coupon;
use Carbon\Carbon;

/**
 * Coupon validation and discount math aligned with mobile COUPON_FLOW / API contract.
 */
final class ShopCouponService
{
    /**
     * @param  array<int>  $cartCategoryIds
     * @param  array<int>  $cartServiceIds
     * @return array{
     *   ok: bool,
     *   message?: string,
     *   coupon_id?: int,
     *   code?: string,
     *   discount_type?: string,
     *   coupon_discount?: float,
     *   free_shipping?: bool,
     *   coupon?: array<string, mixed>,
     *   order_summary?: array<string, mixed>
     * }
     */
    public function preview(
        ?string $code,
        float $subtotal,
        float $catalogDiscount = 0,
        ?int $userId = null,
        array $cartCategoryIds = [],
        ?string $cartCatalog = null,
        array $cartServiceIds = []
    ): array {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return ['ok' => false, 'message' => 'Please enter a coupon code.'];
        }

        $coupon = Coupon::query()->with(['categories', 'services'])->where('code', $code)->first();
        if (! $coupon) {
            return ['ok' => false, 'message' => 'Invalid coupon code.'];
        }

        $subtotal = round(max(0, $subtotal), 2);
        $catalogDiscount = round(max(0, $catalogDiscount), 2);
        $afterCatalog = round(max(0, $subtotal - $catalogDiscount), 2);

        $err = $this->validateEligibility($coupon, $afterCatalog, $userId, $cartCategoryIds, $cartCatalog, $cartServiceIds);
        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        [$couponDiscount, $freeShipping] = $this->computeCouponDiscount($coupon, $afterCatalog);
        $summary = CartController::buildOrderSummaryWithCoupon(
            $subtotal,
            $catalogDiscount,
            $couponDiscount,
            $freeShipping,
            $coupon->code
        );

        return [
            'ok' => true,
            'message' => 'Coupon applied.',
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'coupon_discount' => $couponDiscount,
            'free_shipping' => $freeShipping,
            'coupon' => $this->couponToApi($coupon),
            'order_summary' => $summary,
        ];
    }

    /**
     * @return array{0: float, 1: bool} [coupon_discount, free_shipping]
     */
    public function computeCouponDiscount(Coupon $coupon, float $afterCatalog): array
    {
        $afterCatalog = round(max(0, $afterCatalog), 2);
        $type = strtolower((string) $coupon->discount_type);

        if ($type === 'free_shipping') {
            return [0.0, true];
        }

        if ($type === 'percentage') {
            $pct = (float) ($coupon->discount_value ?? 0);
            $raw = round($afterCatalog * ($pct / 100), 2);
            $max = $coupon->max_discount_amount;
            if ($max !== null && (float) $max > 0) {
                $raw = min($raw, (float) $max);
            }

            return [round(min($raw, $afterCatalog), 2), false];
        }

        if ($type === 'fixed_amount') {
            return [round(min((float) ($coupon->discount_value ?? 0), $afterCatalog), 2), false];
        }

        return [0.0, false];
    }

    /**
     * @param  array<int>  $cartCategoryIds
     * @param  array<int>  $cartServiceIds
     */
    public function validateEligibility(
        Coupon $coupon,
        float $afterCatalog,
        ?int $userId,
        array $cartCategoryIds = [],
        ?string $cartCatalog = null,
        array $cartServiceIds = []
    ): ?string {
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
        if ($afterCatalog + 0.0001 < $min) {
            return 'Minimum order is '.number_format($min, 0).' AED after discounts.';
        }

        if ($coupon->usage_limit !== null && $coupon->usage_limit > 0) {
            if ($coupon->paidOrdersCount() >= $coupon->usage_limit) {
                return 'This coupon has reached its maximum number of uses.';
            }
        }

        if ($userId !== null && $coupon->usage_limit_per_user !== null && $coupon->usage_limit_per_user > 0) {
            if ($coupon->paidOrdersCountForUser($userId) >= $coupon->usage_limit_per_user) {
                return 'You have already used this coupon the maximum number of times.';
            }
        }

        $scopeErr = $this->validateCatalogScope($coupon, $cartCategoryIds, $cartCatalog, $cartServiceIds);
        if ($scopeErr !== null) {
            return $scopeErr;
        }

        return null;
    }

    /**
     * @param  array<int>  $cartCategoryIds
     * @param  array<int>  $cartServiceIds
     */
    public function validateCatalogScope(Coupon $coupon, array $cartCategoryIds, ?string $cartCatalog, array $cartServiceIds = []): ?string
    {
        $appliesTo = strtolower((string) ($coupon->applies_to ?? Coupon::APPLIES_ALL));

        if ($appliesTo === Coupon::APPLIES_CATEGORIES) {
            $allowed = $coupon->relationLoaded('categories')
                ? $coupon->categories->pluck('id')->map(fn ($id) => (int) $id)->all()
                : $coupon->categories()->pluck('categories.id')->all();

            if ($allowed === []) {
                return 'This coupon has no categories configured.';
            }

            $cartCategoryIds = array_map('intval', $cartCategoryIds);
            if ($cartCategoryIds === [] || count(array_intersect($allowed, $cartCategoryIds)) === 0) {
                return 'This coupon does not apply to items in your cart.';
            }

            return null;
        }

        if ($appliesTo === Coupon::APPLIES_SERVICES) {
            $allowed = $coupon->relationLoaded('services')
                ? $coupon->services->pluck('id')->map(fn ($id) => (int) $id)->all()
                : $coupon->services()->pluck('services.id')->all();

            if ($allowed === []) {
                return 'This coupon has no services configured.';
            }

            $cartServiceIds = array_map('intval', $cartServiceIds);
            if ($cartServiceIds === [] || count(array_intersect($allowed, $cartServiceIds)) === 0) {
                return 'This coupon does not apply to services in your cart.';
            }

            return null;
        }

        // applies_to = all (store products)
        if ($cartCatalog !== null && strtolower($cartCatalog) === Coupon::SCOPE_SERVICES) {
            return 'This coupon applies to all store products.';
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
            'applies_to' => $coupon->applies_to ?? Coupon::APPLIES_ALL,
            'catalog_scope' => $coupon->catalog_scope,
            'category_ids' => $coupon->relationLoaded('categories')
                ? $coupon->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                : $coupon->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all(),
            'service_ids' => $coupon->relationLoaded('services')
                ? $coupon->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                : $coupon->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all(),
        ];
    }
}
