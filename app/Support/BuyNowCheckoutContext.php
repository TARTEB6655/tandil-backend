<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public static function remember(int $userId, Request $request): void
    {
        if ($userId <= 0 || ! $request->filled('product_id')) {
            return;
        }

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

        Cache::put(self::cacheKey($userId), $payload, now()->addHours(self::TTL_HOURS));
    }

    /**
     * Fill missing Buy Now fields when the app continues checkout with is_buy_now only.
     */
    public static function hydrateOntoRequest(Request $request, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        if (! $request->boolean('is_buy_now') && ! $request->boolean('use_buy_now_context')) {
            return;
        }

        if ($request->filled('product_id')) {
            return;
        }

        $cached = Cache::get(self::cacheKey($userId));
        if (! is_array($cached) || empty($cached['product_id'])) {
            return;
        }

        foreach ($cached as $key => $value) {
            if (! $request->has($key)) {
                $request->merge([$key => $value]);
            }
        }

        if (! $request->has('is_buy_now')) {
            $request->merge(['is_buy_now' => true]);
        }
    }

    public static function forget(int $userId): void
    {
        if ($userId > 0) {
            Cache::forget(self::cacheKey($userId));
        }
    }

    private static function cacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.$userId;
    }
}
