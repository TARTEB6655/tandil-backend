<?php

namespace App\Support;

use App\Models\Product;

/**
 * Area-based (per m²) pricing for service products only.
 * Matches Admin "Product Settings" UI: Fixed Price vs Price per Square Meter.
 */
final class ServiceAreaPricing
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PER_M2 = 'per_m2';

    /** @var list<string> */
    public const INCLUDE_KEYS = [
        'materials',
        'installation',
        'labor',
        'transportation',
        'delivery',
    ];

    /** @var array<string, string> */
    public const INCLUDE_LABELS = [
        'materials' => 'Materials',
        'installation' => 'Installation',
        'labor' => 'Labor',
        'transportation' => 'Transportation',
        'delivery' => 'Delivery',
    ];

    public static function normalizeType(?string $type, bool $isService): string
    {
        $type = strtolower(trim((string) $type));
        if (! $isService) {
            return self::TYPE_FIXED;
        }
        if (in_array($type, [self::TYPE_PER_M2, 'per_sqm', 'sqm', 'm2', 'area'], true)) {
            return self::TYPE_PER_M2;
        }

        return self::TYPE_FIXED;
    }

    public static function isPerM2(Product $product): bool
    {
        if (($product->type ?? 'product') !== 'service') {
            return false;
        }

        return self::normalizeType($product->pricing_type ?? null, true) === self::TYPE_PER_M2;
    }

    /**
     * Normalize price_includes from request (object, array of keys, or JSON string).
     *
     * @param  mixed  $raw
     * @return array<string, bool>|null null when not a service / not provided meaningfully
     */
    public static function normalizeIncludes(mixed $raw, bool $isService): ?array
    {
        if (! $isService) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw)) {
            return self::emptyIncludes();
        }

        // Accept ["materials","labor"] style
        $isList = array_keys($raw) === range(0, count($raw) - 1);
        if ($isList) {
            $selected = [];
            foreach ($raw as $key) {
                $k = strtolower(trim((string) $key));
                if (in_array($k, self::INCLUDE_KEYS, true)) {
                    $selected[$k] = true;
                }
            }
            $out = self::emptyIncludes();
            foreach ($out as $key => $_) {
                $out[$key] = (bool) ($selected[$key] ?? false);
            }

            return $out;
        }

        $out = self::emptyIncludes();
        foreach (self::INCLUDE_KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
            } elseif (array_key_exists('includes_'.$key, $raw)) {
                $out[$key] = filter_var($raw['includes_'.$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $out;
    }

    /**
     * @return array<string, bool>
     */
    public static function emptyIncludes(): array
    {
        return array_fill_keys(self::INCLUDE_KEYS, false);
    }

    /**
     * @param  array<string, bool>|null  $includes
     * @return list<string>
     */
    public static function includeLabels(?array $includes): array
    {
        if (! is_array($includes)) {
            return [];
        }
        $labels = [];
        foreach (self::INCLUDE_KEYS as $key) {
            if (! empty($includes[$key])) {
                $labels[] = self::INCLUDE_LABELS[$key];
            }
        }

        return $labels;
    }

    public static function formatMoney(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return 'AED '.$formatted;
    }

    /**
     * Customer / admin product payload fields for pricing UI.
     *
     * @return array<string, mixed>
     */
    public static function productApiFields(Product $product): array
    {
        $isService = ($product->type ?? 'product') === 'service';
        $type = self::normalizeType($product->pricing_type ?? null, $isService);
        $price = round((float) $product->price, 2);
        $includes = $isService
            ? (is_array($product->price_includes) ? array_merge(self::emptyIncludes(), $product->price_includes) : self::emptyIncludes())
            : null;

        if ($isService && $type === self::TYPE_PER_M2) {
            return [
                'pricing_type' => self::TYPE_PER_M2,
                'price' => $price,
                'price_per_m2' => $price,
                'currency' => 'AED',
                'price_unit' => 'm²',
                'price_label' => self::formatMoney($price).' / m²',
                'requires_area' => true,
                'price_includes' => $includes,
                'price_includes_labels' => self::includeLabels($includes),
                'customer_preview' => [
                    'price_display' => 'Price: '.self::formatMoney($price).' / m²',
                    'note' => 'Customer must enter Required Area (m²). Total = Area × Price per m².',
                    'example' => [
                        'area' => 100,
                        'price_per_m2' => $price,
                        'total' => round(100 * $price, 2),
                        'total_label' => self::formatMoney(round(100 * $price, 2)),
                    ],
                ],
            ];
        }

        return [
            'pricing_type' => self::TYPE_FIXED,
            'price' => $price,
            'price_per_m2' => null,
            'currency' => 'AED',
            'price_unit' => null,
            'price_label' => self::formatMoney($price),
            'requires_area' => false,
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'customer_preview' => $isService ? [
                'price_display' => 'Price: '.self::formatMoney($price),
                'note' => 'On the customer app: show a single fixed price. No area field is required for checkout.',
            ] : null,
        ];
    }

    public static function normalizeArea(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $area = round((float) $value, 2);

        return $area > 0 ? $area : null;
    }

    public static function validateAreaMessage(Product $product, mixed $areaRaw): ?string
    {
        if (! self::isPerM2($product)) {
            return null;
        }
        $area = self::normalizeArea($areaRaw);
        if ($area === null) {
            return 'Required Area (m²) is required and must be a positive number (e.g. 50, 100, 137.5).';
        }

        return null;
    }

    /**
     * Line total for a cart/order line.
     * Fixed: quantity × unitPrice. Per m²: required_area × unitPrice (quantity ignored for money).
     */
    public static function lineTotal(Product $product, float $unitPrice, int $quantity, ?float $requiredArea): float
    {
        if (self::isPerM2($product)) {
            $area = $requiredArea !== null && $requiredArea > 0 ? $requiredArea : 0.0;

            return round($area * $unitPrice, 2);
        }

        return round(max(1, $quantity) * $unitPrice, 2);
    }

    /**
     * Quantity stored on cart/order for per-m² lines (always 1 — area drives the total).
     */
    public static function effectiveQuantity(Product $product, int $quantity): int
    {
        return self::isPerM2($product) ? 1 : max(1, $quantity);
    }

    /**
     * Snapshot fields for order_items.
     *
     * @return array{pricing_type: string, required_area: ?float, price_includes: ?array}
     */
    public static function orderItemSnapshot(Product $product, ?float $requiredArea): array
    {
        $isService = ($product->type ?? 'product') === 'service';
        $type = self::normalizeType($product->pricing_type ?? null, $isService);

        return [
            'pricing_type' => $type,
            'required_area' => $type === self::TYPE_PER_M2 ? $requiredArea : null,
            'price_includes' => $isService && is_array($product->price_includes)
                ? array_merge(self::emptyIncludes(), $product->price_includes)
                : ($isService ? self::emptyIncludes() : null),
        ];
    }

    /**
     * Fields to expose on cart / order line APIs.
     *
     * @return array<string, mixed>
     */
    public static function lineApiFields(Product $product, float $unitPrice, int $quantity, ?float $requiredArea): array
    {
        $isPerM2 = self::isPerM2($product);
        $area = $isPerM2 ? $requiredArea : null;
        $lineTotal = self::lineTotal($product, $unitPrice, $quantity, $requiredArea);
        $includes = $isPerM2 || ($product->type ?? '') === 'service'
            ? (is_array($product->price_includes) ? array_merge(self::emptyIncludes(), $product->price_includes) : self::emptyIncludes())
            : null;

        return [
            'pricing_type' => $isPerM2 ? self::TYPE_PER_M2 : self::TYPE_FIXED,
            'requires_area' => $isPerM2,
            'required_area' => $area,
            'area_unit' => $isPerM2 ? 'm²' : null,
            'unit_price' => $unitPrice,
            'unit_price_label' => $isPerM2
                ? self::formatMoney($unitPrice).' / m²'
                : self::formatMoney($unitPrice),
            'line_total' => $lineTotal,
            'line_total_label' => self::formatMoney($lineTotal),
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'pricing_breakdown' => $isPerM2 && $area !== null ? [
                'area' => $area,
                'area_label' => rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.').' m²',
                'unit_price' => $unitPrice,
                'unit_price_label' => self::formatMoney($unitPrice).'/m²',
                'total' => $lineTotal,
                'total_label' => self::formatMoney($lineTotal),
                'formula' => $area.' × '.$unitPrice.' = '.$lineTotal,
            ] : null,
        ];
    }

    /**
     * Order-item API fields from persisted snapshot (product may have changed).
     *
     * @return array<string, mixed>
     */
    public static function orderItemApiFields(\App\Models\OrderItem $item): array
    {
        $type = self::normalizeType($item->pricing_type ?? null, true);
        $isPerM2 = $type === self::TYPE_PER_M2;
        $unitPrice = round((float) $item->price, 2);
        $area = $item->required_area !== null ? round((float) $item->required_area, 2) : null;
        $includes = is_array($item->price_includes)
            ? array_merge(self::emptyIncludes(), $item->price_includes)
            : null;
        $lineTotal = round((float) $item->subtotal, 2);

        return [
            'pricing_type' => $type,
            'required_area' => $isPerM2 ? $area : null,
            'area_unit' => $isPerM2 ? 'm²' : null,
            'unit_price' => $unitPrice,
            'unit_price_label' => $isPerM2
                ? self::formatMoney($unitPrice).' / m²'
                : self::formatMoney($unitPrice),
            'line_total' => $lineTotal,
            'line_total_label' => self::formatMoney($lineTotal),
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'pricing_breakdown' => $isPerM2 && $area !== null ? [
                'area' => $area,
                'area_label' => rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.').' m²',
                'unit_price' => $unitPrice,
                'unit_price_label' => self::formatMoney($unitPrice).'/m²',
                'total' => $lineTotal,
                'total_label' => self::formatMoney($lineTotal),
                'formula' => $area.' × '.$unitPrice.' = '.$lineTotal,
            ] : null,
        ];
    }
}
