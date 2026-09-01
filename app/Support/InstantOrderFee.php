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

    public static function amount(): float
    {
        $raw = Setting::get(self::SETTING_KEY);

        if ($raw === null || $raw === '') {
            return 0.0;
        }

        return max(0, round((float) $raw, 2));
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminApiPayload(): array
    {
        $amount = self::amount();

        return [
            'instant_order_fee_amount' => $amount,
            'currency' => config('shop.currency', 'AED'),
            'enabled' => $amount > 0,
            'applies_to' => 'instant_orders',
            'note' => 'Flat surcharge added to direct product checkout (vendor/platform). Not applied to booking/service orders.',
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

        if ($fee > 0) {
            $summary['instant_order_fee_label'] = 'Instant order fee';
            $summary['total'] = round((float) ($summary['total'] ?? 0) + $fee, 2);
        }

        return $summary;
    }

    private static function productFromLine(mixed $item): ?Product
    {
        if ($item instanceof Cart) {
            return $item->product;
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
