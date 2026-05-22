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

        $err = $this->validateEligibility($coupon, $afterCatalog, $userId, $cartCategoryIds, $cartCatalog, $cartServiceIds, $subtotal);
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
        array $cartServiceIds = [],
        ?float $orderSubtotal = null
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
        $minBasis = round(max(0, $orderSubtotal ?? $afterCatalog), 2);
        if ($minBasis + 0.0001 < $min) {
            return 'Minimum order is '.number_format($min, 0).' AED.';
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
                return 'This offer applies to specific categories. Your cart does not include eligible category items.';
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
                return 'This offer applies to specific services. Your cart does not include eligible service items.';
            }

            return null;
        }

        return null;
    }

    /**
     * Coupons for client browse: store-wide (all products), category PLP, or service PLP.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>, scope: string}
     */
    public function listForBrowse(?int $categoryId = null, ?int $serviceId = null, bool $storewideAll = false): array
    {
        $coupons = Coupon::query()
            ->with(['categories', 'services'])
            ->active()
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', Carbon::today());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', Carbon::today());
            })
            ->orderByDesc('id')
            ->get();

        $scope = $storewideAll ? 'all' : ($categoryId !== null ? 'category' : 'service');

        $rows = [];
        foreach ($coupons as $coupon) {
            if ($storewideAll) {
                if (strtolower((string) ($coupon->applies_to ?? Coupon::APPLIES_ALL)) !== Coupon::APPLIES_ALL) {
                    continue;
                }
            } elseif (! $this->visibleOnBrowse($coupon, $categoryId, $serviceId)) {
                continue;
            }
            $rows[] = $this->offerCard($coupon, true, null);
        }

        return [
            'data' => $rows,
            'meta' => ['total' => count($rows), 'scope' => $scope],
            'scope' => $scope,
        ];
    }

    /**
     * Checkout "Choose a promo code" — split eligible vs not eligible for current cart.
     *
     * @param  array<int>  $cartCategoryIds
     * @param  array<int>  $cartServiceIds
     * @return array{
     *   available_for_order: array<int, array<string, mixed>>,
     *   not_eligible_for_cart: array<int, array<string, mixed>>,
     *   available_count: int,
     *   not_eligible_count: int
     * }
     */
    public function listForCheckout(
        float $subtotal,
        float $catalogDiscount,
        ?int $userId,
        array $cartCategoryIds = [],
        ?string $cartCatalog = null,
        array $cartServiceIds = []
    ): array {
        $subtotal = round(max(0, $subtotal), 2);
        $catalogDiscount = round(max(0, $catalogDiscount), 2);
        $afterCatalog = round(max(0, $subtotal - $catalogDiscount), 2);

        $coupons = Coupon::query()
            ->with(['categories', 'services'])
            ->active()
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', Carbon::today());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', Carbon::today());
            })
            ->orderBy('code')
            ->get();

        $available = [];
        $notEligible = [];

        foreach ($coupons as $coupon) {
            $reason = $this->checkoutIneligibilityReason(
                $coupon,
                $afterCatalog,
                $userId,
                $cartCategoryIds,
                $cartCatalog,
                $cartServiceIds,
                $subtotal
            );

            if ($reason === null) {
                $card = $this->offerCard($coupon, true, null, $subtotal, $catalogDiscount);
                $available[] = $card;
            } else {
                $notEligible[] = $this->offerCard($coupon, false, $reason, $subtotal, $catalogDiscount);
            }
        }

        return [
            'available_for_order' => array_values($available),
            'not_eligible_for_cart' => array_values($notEligible),
            'available_count' => count($available),
            'not_eligible_count' => count($notEligible),
        ];
    }

    public function visibleOnBrowse(Coupon $coupon, ?int $categoryId, ?int $serviceId): bool
    {
        $appliesTo = strtolower((string) ($coupon->applies_to ?? Coupon::APPLIES_ALL));

        if ($appliesTo === Coupon::APPLIES_ALL) {
            return true;
        }

        if ($categoryId !== null && $appliesTo === Coupon::APPLIES_CATEGORIES) {
            $allowed = $this->couponCategoryIds($coupon);

            return $allowed !== [] && in_array($categoryId, $allowed, true);
        }

        if ($serviceId !== null && $appliesTo === Coupon::APPLIES_SERVICES) {
            $allowed = $this->couponServiceIds($coupon);

            return $allowed !== [] && in_array($serviceId, $allowed, true);
        }

        return false;
    }

    /**
     * @param  array<int>  $cartCategoryIds
     * @param  array<int>  $cartServiceIds
     */
    private function checkoutIneligibilityReason(
        Coupon $coupon,
        float $afterCatalog,
        ?int $userId,
        array $cartCategoryIds,
        ?string $cartCatalog,
        array $cartServiceIds,
        float $subtotal
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

        $scopeErr = $this->validateCatalogScope($coupon, $cartCategoryIds, $cartCatalog, $cartServiceIds);
        if ($scopeErr !== null) {
            return $scopeErr;
        }

        $min = (float) ($coupon->min_order_amount ?? 0);
        if (round(max(0, $subtotal), 2) + 0.0001 < $min) {
            return 'Minimum order is '.number_format($min, 0).' AED.';
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

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function offerCard(
        Coupon $coupon,
        bool $eligible,
        ?string $ineligibleReason,
        ?float $subtotal = null,
        ?float $catalogDiscount = null
    ): array {
        $previewDiscount = null;
        if ($eligible && $subtotal !== null && $catalogDiscount !== null) {
            $afterCatalog = round(max(0, $subtotal - $catalogDiscount), 2);
            [$previewDiscount] = $this->computeCouponDiscount($coupon, $afterCatalog);
        }

        $categories = $coupon->relationLoaded('categories')
            ? $coupon->categories->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])->values()->all()
            : [];
        $services = $coupon->relationLoaded('services')
            ? $coupon->services->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])->values()->all()
            : [];

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'title' => $coupon->title,
            'description' => $coupon->description,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value !== null ? (float) $coupon->discount_value : null,
            'discount_label' => $this->discountLabel($coupon),
            'min_order_amount' => (float) ($coupon->min_order_amount ?? 0),
            'max_discount_amount' => $coupon->max_discount_amount !== null ? (float) $coupon->max_discount_amount : null,
            'starts_at' => $coupon->starts_at?->toDateString(),
            'ends_at' => $coupon->ends_at?->toDateString(),
            'usage_limit' => $coupon->usage_limit,
            'usage_limit_per_user' => $coupon->usage_limit_per_user,
            'applies_to' => $coupon->applies_to ?? Coupon::APPLIES_ALL,
            'applies_to_label' => $this->appliesToLabel($coupon),
            'scope_label' => $this->scopeLabel($coupon),
            'scope_summary' => $this->scopeSummary($coupon),
            'category_ids' => $this->couponCategoryIds($coupon),
            'service_ids' => $this->couponServiceIds($coupon),
            'categories' => $categories,
            'services' => $services,
            'eligible' => $eligible,
            'ineligible_reason' => $ineligibleReason,
            'coupon_discount_preview' => $previewDiscount,
        ];
    }

    public function discountLabel(Coupon $coupon): string
    {
        $type = strtolower((string) $coupon->discount_type);
        if ($type === 'percentage') {
            $v = rtrim(rtrim(number_format((float) ($coupon->discount_value ?? 0), 2), '0'), '.');

            return $v.'% OFF';
        }

        return number_format((float) ($coupon->discount_value ?? 0), 0).' AED OFF';
    }

    private function appliesToLabel(Coupon $coupon): string
    {
        return match (strtolower((string) ($coupon->applies_to ?? Coupon::APPLIES_ALL))) {
            Coupon::APPLIES_CATEGORIES => 'Specific categories',
            Coupon::APPLIES_SERVICES => 'Specific services',
            default => 'All products',
        };
    }

    private function scopeLabel(Coupon $coupon): string
    {
        $appliesTo = strtolower((string) ($coupon->applies_to ?? Coupon::APPLIES_ALL));

        if ($appliesTo === Coupon::APPLIES_CATEGORIES) {
            $names = $coupon->relationLoaded('categories')
                ? $coupon->categories->pluck('name')->filter()->values()->all()
                : [];
            if ($names === []) {
                return 'Category';
            }
            $first = (string) $names[0];

            return 'Category: '.$first;
        }

        if ($appliesTo === Coupon::APPLIES_SERVICES) {
            $names = $coupon->relationLoaded('services')
                ? $coupon->services->pluck('name')->filter()->values()->all()
                : [];
            if ($names === []) {
                return 'Service';
            }
            $first = (string) $names[0];

            return 'Service: '.$first;
        }

        return 'All products';
    }

    private function scopeSummary(Coupon $coupon): string
    {
        $appliesTo = strtolower((string) ($coupon->applies_to ?? Coupon::APPLIES_ALL));

        if ($appliesTo === Coupon::APPLIES_CATEGORIES) {
            $names = $coupon->relationLoaded('categories')
                ? $coupon->categories->pluck('name')->filter()->values()->all()
                : [];
            if ($names === []) {
                return 'Categories';
            }
            if (count($names) === 1) {
                return 'Categories: '.$names[0];
            }

            return 'Categories: +'.(count($names) - 1).' more';
        }

        if ($appliesTo === Coupon::APPLIES_SERVICES) {
            $names = $coupon->relationLoaded('services')
                ? $coupon->services->pluck('name')->filter()->values()->all()
                : [];
            if ($names === []) {
                return 'Services';
            }
            if (count($names) === 1) {
                return 'Services: '.$names[0];
            }

            return 'Services: +'.(count($names) - 1).' more';
        }

        return 'All products';
    }

    /**
     * @return array<int, int>
     */
    private function couponCategoryIds(Coupon $coupon): array
    {
        return $coupon->relationLoaded('categories')
            ? $coupon->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $coupon->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<int, int>
     */
    private function couponServiceIds(Coupon $coupon): array
    {
        return $coupon->relationLoaded('services')
            ? $coupon->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $coupon->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();
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
            'discount_label' => $this->discountLabel($coupon),
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
