<?php

namespace App\Support;

use App\Models\ShopBuyNowContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile Buy Now: product screen calls buy-now/summary, then checkout/review and payment-intent
 * often omit product_id and fall back to the DB cart (wrong subtotal). Remember last Buy Now body per user.
 */
class BuyNowCheckoutContext
{
    private const CACHE_PREFIX = 'shop:buy_now_checkout:';

    private const TTL_HOURS = 6;

    /** @var list<string> */
    private const KEYS = [
        'product_id',
        'quantity',
        'qty',
        'selected_option_ids',
        'option_ids',
        'selectedOptionIds',
        'optionIds',
        'area_pricing_mode',
        'areaPricingMode',
        'pricing_mode',
        'required_area',
        'requiredArea',
        'area',
        'area_m2',
        'm2',
        'tree_quantity',
        'trees',
        'palm_tree_quantity',
        'palms',
        'booking_date',
        'bookingDate',
        'booking_slot',
        'bookingSlot',
    ];

    /** @var list<string> */
    private const AREA_KEYS = [
        'required_area',
        'requiredArea',
        'area',
        'area_m2',
        'm2',
    ];

    /** @var list<string> */
    private const NON_PRICING_KEYS = [
        'quantity',
        'qty',
        'selected_option_ids',
        'option_ids',
        'selectedOptionIds',
        'optionIds',
        'tree_quantity',
        'trees',
        'palm_tree_quantity',
        'palms',
        'booking_date',
        'bookingDate',
        'booking_slot',
        'bookingSlot',
    ];

    public static function remember(int $userId, Request $request): void
    {
        if ($userId <= 0 || ! $request->filled('product_id')) {
            return;
        }

        $payload = self::payloadFromRequest($request);
        self::stripAreaFromStoredPayloadWhenNotPerM2($payload);
        $payload['saved_at'] = now()->toIso8601String();

        self::persistPayload($userId, $payload);
        if ($request->filled('product_id')) {
            $normalized = ServiceAreaPricing::normalizeAreaPricingMode(
                self::rawPricingModeFromArray($payload)
            );
            if ($normalized !== ServiceAreaPricing::TYPE_PER_M2) {
                ServiceAreaPricing::forgetBuyNowArea($userId, (int) $request->input('product_id'));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function persistPayload(int $userId, array $payload): void
    {
        if (self::databaseReady()) {
            ShopBuyNowContext::query()->updateOrCreate(
                ['user_id' => $userId],
                ['payload' => $payload, 'updated_at' => now()]
            );
        }

        Cache::put(self::cacheKey($userId), $payload, now()->addHours(self::TTL_HOURS));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadPayload(int $userId): ?array
    {
        if (self::databaseReady()) {
            $row = ShopBuyNowContext::query()->find($userId);
            if ($row !== null && is_array($row->payload) && $row->payload !== []) {
                return $row->payload;
            }
        }

        $cached = Cache::get(self::cacheKey($userId));

        return is_array($cached) ? $cached : null;
    }

    private static function databaseReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $ready = Schema::hasTable('shop_buy_now_contexts');
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadFromRequest(Request $request): array
    {
        $payload = ['product_id' => (int) $request->input('product_id')];
        foreach (self::KEYS as $key) {
            if ($key === 'product_id' || ! $request->has($key)) {
                continue;
            }
            $value = $request->input($key);
            if ($value === null || $value === '') {
                continue;
            }
            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * Fill missing Buy Now fields when checkout continues without repeating the product screen body.
     */
    public static function hydrateOntoRequest(Request $request, int $userId): void
    {
        if ($userId <= 0 || self::requestIsExplicitCartCheckout($request)) {
            return;
        }

        $cached = self::loadPayload($userId);
        if (! is_array($cached) || empty($cached['product_id'])) {
            self::normalizePricingInputsOnRequest($request, $userId);

            return;
        }

        if ($request->filled('product_id')) {
            if ((int) $request->input('product_id') !== (int) $cached['product_id']) {
                self::normalizePricingInputsOnRequest($request, $userId);

                return;
            }

            if (
                self::requestHasExplicitPricingChoice($request)
                || self::requestPathIs($request, 'buy-now/summary')
            ) {
                // Product screen sent a fresh pricing choice — never reuse stale m² from cache.
                self::mergeMissingFromCache($request, $cached, self::NON_PRICING_KEYS);
            } else {
                self::mergeMissingFromCache($request, $cached, self::KEYS);
            }

            if (! $request->has('is_buy_now')) {
                $request->merge(['is_buy_now' => true]);
            }

            self::normalizePricingInputsOnRequest($request, $userId);

            return;
        }

        if (
            ! $request->boolean('is_buy_now')
            && ! $request->boolean('use_buy_now_context')
            && ! self::shouldAutoApplyCachedBuyNow($request)
        ) {
            return;
        }

        self::mergeMissingFromCache($request, $cached, self::KEYS);
        if ($request->filled('product_id')) {
            if (! $request->has('is_buy_now')) {
                $request->merge(['is_buy_now' => true]);
            }
        }

        self::normalizePricingInputsOnRequest($request, $userId);
    }

    /**
     * @param  array<string, mixed>  $cached
     * @param  list<string>  $allowedKeys
     */
    private static function mergeMissingFromCache(Request $request, array $cached, array $allowedKeys): void
    {
        $allowed = array_flip($allowedKeys);
        foreach ($cached as $key => $value) {
            if ($key === 'saved_at' || $request->has($key)) {
                continue;
            }
            if ($allowed !== [] && ! isset($allowed[$key])) {
                continue;
            }
            $request->merge([$key => $value]);
        }

        if (! $request->filled('product_id') && ! empty($cached['product_id'])) {
            $request->merge(['product_id' => (int) $cached['product_id']]);
        }
    }

    /**
     * After m² → fixed/catalog switch, drop stale area from request + area cache.
     */
    public static function normalizePricingInputsOnRequest(Request $request, int $userId): void
    {
        if (! $request->filled('product_id')) {
            return;
        }

        $productId = (int) $request->input('product_id');
        $rawMode = self::rawPricingModeFromRequest($request);
        $normalized = ServiceAreaPricing::normalizeAreaPricingMode($rawMode);

        if (ServiceAreaPricing::isExplicitCatalogCheckoutMode($rawMode)) {
            self::stripAreaFieldsFromRequest($request);
            ServiceAreaPricing::forgetBuyNowArea($userId, $productId);

            return;
        }

        if ($normalized === ServiceAreaPricing::TYPE_FIXED) {
            self::stripAreaFieldsFromRequest($request);
            ServiceAreaPricing::forgetBuyNowArea($userId, $productId);

            return;
        }

        if ($normalized === ServiceAreaPricing::TYPE_PER_M2) {
            $area = ServiceAreaPricing::normalizeArea(
                ServiceAreaPricing::resolveAreaFromRequest($request, false)
            );
            if ($area === null && ! self::requestHasExplicitPricingChoice($request)) {
                self::stripAreaFieldsFromRequest($request);
            }
        }
    }

    public static function requestHasExplicitPricingChoice(Request $request): bool
    {
        $rawMode = self::rawPricingModeFromRequest($request);
        if ($rawMode !== null && $rawMode !== '') {
            return true;
        }

        return ServiceAreaPricing::normalizeArea(
            ServiceAreaPricing::resolveAreaFromRequest($request, false)
        ) !== null;
    }

    private static function rawPricingModeFromRequest(Request $request): mixed
    {
        foreach (['area_pricing_mode', 'areaPricingMode', 'pricing_mode', 'pricingMode'] as $key) {
            if ($request->has($key)) {
                return $request->input($key);
            }
        }

        return null;
    }

    public static function stripAreaFieldsFromRequest(Request $request): void
    {
        foreach (self::AREA_KEYS as $key) {
            if ($request->request->has($key)) {
                $request->request->remove($key);
            }
            if ($request->query->has($key)) {
                $request->query->remove($key);
            }
        }
    }

    public static function requestIsExplicitCartCheckout(Request $request): bool
    {
        if ($request->boolean('checkout_as_cart')) {
            return true;
        }

        return in_array((string) $request->input('checkout_mode'), ['cart', 'server_cart'], true);
    }

    public static function shouldAutoApplyCachedBuyNow(Request $request): bool
    {
        return self::requestPathIs($request, 'checkout/review')
            || self::requestPathIs($request, 'order-summary')
            || self::requestPathIs($request, 'stripe/payment-intent');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function stripAreaFromStoredPayloadWhenNotPerM2(array &$payload): void
    {
        $rawMode = self::rawPricingModeFromArray($payload);
        if (ServiceAreaPricing::isExplicitCatalogCheckoutMode($rawMode)) {
            foreach (self::AREA_KEYS as $key) {
                unset($payload[$key]);
            }

            return;
        }

        $normalized = ServiceAreaPricing::normalizeAreaPricingMode($rawMode);
        if ($normalized === ServiceAreaPricing::TYPE_FIXED) {
            foreach (self::AREA_KEYS as $key) {
                unset($payload[$key]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function rawPricingModeFromArray(array $payload): mixed
    {
        foreach (['area_pricing_mode', 'areaPricingMode', 'pricing_mode', 'pricingMode'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    public static function requestPathIs(Request $request, string $segment): bool
    {
        $path = str_replace('\\', '/', strtolower(trim($request->path(), '/')));

        return str_contains($path, strtolower($segment));
    }

    public static function forget(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        if (self::databaseReady()) {
            ShopBuyNowContext::query()->where('user_id', $userId)->delete();
        }

        Cache::forget(self::cacheKey($userId));
    }

    private static function cacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.$userId;
    }
}
