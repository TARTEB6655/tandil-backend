<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Setting;

/**
 * Admin-configurable surcharge for Instant Orders (product checkout, not booking/service).
 */
final class InstantOrderFee
{
    public const SETTING_KEY = 'shop_instant_order_fee_amount';

    public const ENABLED_KEY = 'shop_instant_order_fee_enabled';

    public static function storedAmount(): float
    {
        $raw = Setting::get(self::SETTING_KEY);

        if ($raw === null || $raw === '') {
            return 0.0;
        }

        return max(0, round((float) $raw, 2));
    }

    public static function enabled(): bool
    {
        $raw = Setting::get(self::ENABLED_KEY);
        // Legacy: if enabled flag missing, treat amount > 0 as enabled.
        if ($raw === null || $raw === '') {
            return self::storedAmount() > 0;
        }

        return in_array((string) $raw, ['1', 'true', 'yes', 'on'], true);
    }

    /** Effective fee used at checkout (0 when disabled). */
    public static function amount(): float
    {
        if (! self::enabled()) {
            return 0.0;
        }

        return self::storedAmount();
    }

    /**
     * Persist amount + optional enabled toggle (admin UI).
     *
     * @param  array<string, mixed>  $input
     */
    public static function saveFromRequest(array $input): void
    {
        $amount = null;
        foreach ([
            'instant_order_fee_amount',
            'extra_fee_amount',
            'amount',
            'fee_amount',
            'fee',
        ] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
                $amount = max(0, round((float) $input[$key], 2));
                break;
            }
        }

        $enabled = null;
        foreach ([
            'instant_order_fee_enabled',
            'extra_fee_enabled',
            'enabled',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $enabled = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($enabled === null) {
                    $enabled = in_array(strtolower((string) $input[$key]), ['1', 'true', 'yes', 'on'], true);
                }
                break;
            }
        }

        if ($amount !== null) {
            Setting::set(self::SETTING_KEY, (string) $amount, 'text', 'shop');
        }

        if ($enabled !== null) {
            Setting::set(self::ENABLED_KEY, $enabled ? '1' : '0', 'text', 'shop');
            // Turning on with no prior amount keeps stored amount; turning off keeps amount for later.
            if ($enabled && $amount === null && self::storedAmount() <= 0 && isset($input['instant_order_fee_amount'])) {
                Setting::set(self::SETTING_KEY, (string) max(0, (float) $input['instant_order_fee_amount']), 'text', 'shop');
            }
        } elseif ($amount !== null) {
            // Amount-only save: enable when > 0, disable when 0.
            Setting::set(self::ENABLED_KEY, $amount > 0 ? '1' : '0', 'text', 'shop');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminApiPayload(): array
    {
        $stored = self::storedAmount();
        $enabled = self::enabled();

        return [
            'instant_order_fee_amount' => $stored,
            'instant_order_fee_enabled' => $enabled,
            'enabled' => $enabled,
            'currency' => config('shop.currency', 'AED'),
            'applies_to' => 'instant_orders',
            'note' => 'Flat surcharge added to direct product checkout (vendor/platform). Not applied to booking/service orders. Appears on GET /api/shop/cart and /api/shop/order-summary as instant_order_fee.',
        ];
    }

    /**
     * Instant = at least one product line and no service/booking lines.
     */
    public static function cartIsInstant(?iterable $cartItems): bool
    {
        if ($cartItems === null) {
            return false;
        }

        $hasProduct = false;
        $hasService = false;

        foreach ($cartItems as $item) {
            $product = self::productFromLine($item);
            if ($product === null) {
                continue;
            }

            if (OrderFulfillmentType::isServiceProduct($product)) {
                $hasService = true;
            } elseif (OrderFulfillmentType::isProductLine($product)) {
                $hasProduct = true;
            }
        }

        return $hasProduct && ! $hasService;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public static function applyToSummary(array $summary, ?iterable $cartItems = null): array
    {
        $isInstant = self::cartIsInstant($cartItems);
        $fee = $isInstant ? self::amount() : 0.0;

        $summary['is_instant_order'] = $isInstant;
        $summary['instant_order_fee'] = $fee;
        $summary['instant_order_fee_label'] = $fee > 0 ? 'Instant order fee' : null;

        if ($fee > 0) {
            $summary['total'] = round((float) ($summary['total'] ?? 0) + $fee, 2);
        }

        return $summary;
    }

    private static function productFromLine(mixed $item): ?Product
    {
        if ($item instanceof Cart) {
            return $item->relationLoaded('product') ? $item->product : $item->product()->first();
        }

        if (! is_array($item)) {
            return null;
        }

        if (isset($item['product']) && $item['product'] instanceof Product) {
            return $item['product'];
        }

        $productId = $item['product_id'] ?? null;
        if ($productId) {
            return Product::query()->find((int) $productId);
        }

        return null;
    }
}
