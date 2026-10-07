<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Optional tree / palm-tree quantity pricing for service products only.
 *
 * Admin configures unit prices + visibility on Product Settings (global service pricing).
 * Customer may skip quantities and keep the normal/base service price, or enter counts
 * so checkout adds (trees × price_per_tree) + (palms × price_per_palm_tree).
 */
final class ServiceTreePricing
{
    public const SETTING_SHOW = 'service_show_tree_options';

    public const SETTING_PRICE_PER_TREE = 'service_price_per_tree';

    public const SETTING_PRICE_PER_PALM = 'service_price_per_palm_tree';

    /**
     * @return array{
     *     show_tree_options: bool,
     *     price_per_tree: float,
     *     price_per_palm_tree: float
     * }
     */
    public static function globalConfig(): array
    {
        $showRaw = Setting::get(self::SETTING_SHOW, '0');
        $treeRaw = Setting::get(self::SETTING_PRICE_PER_TREE, '0');
        $palmRaw = Setting::get(self::SETTING_PRICE_PER_PALM, '0');

        return [
            'show_tree_options' => filter_var($showRaw, FILTER_VALIDATE_BOOLEAN),
            'price_per_tree' => max(0, round((float) ($treeRaw === null || $treeRaw === '' ? 0 : $treeRaw), 2)),
            'price_per_palm_tree' => max(0, round((float) ($palmRaw === null || $palmRaw === '' ? 0 : $palmRaw), 2)),
        ];
    }

    public static function saveGlobal(bool $show, float $pricePerTree, float $pricePerPalmTree): void
    {
        Setting::set(self::SETTING_SHOW, $show ? '1' : '0', 'boolean', 'services');
        Setting::set(self::SETTING_PRICE_PER_TREE, (string) max(0, round($pricePerTree, 2)), 'text', 'services');
        Setting::set(self::SETTING_PRICE_PER_PALM, (string) max(0, round($pricePerPalmTree, 2)), 'text', 'services');
    }

    /**
     * Whether the customer UI should offer tree/palm quantity inputs for this product.
     */
    public static function isEnabledForProduct(Product $product): bool
    {
        if (! ServiceAreaPricing::appliesToProduct($product)) {
            return false;
        }

        $config = self::globalConfig();
        if (! $config['show_tree_options']) {
            return false;
        }

        return $config['price_per_tree'] > 0 || $config['price_per_palm_tree'] > 0;
    }

    /**
     * Admin Product Settings fields (global — same screen as Fixed / per m²).
     *
     * @return array<string, mixed>
     */
    public static function adminSettingsFields(): array
    {
        $config = self::globalConfig();
        $show = $config['show_tree_options'];
        $tree = $config['price_per_tree'];
        $palm = $config['price_per_palm_tree'];

        return [
            'show_tree_options' => $show,
            'show_on_customer_service_products' => $show,
            'price_per_tree' => $tree,
            'price_per_palm_tree' => $palm,
            'tree_pricing' => [
                'show' => $show,
                'toggle_label' => 'Show on customer service products',
                'toggle_description' => 'When off, the tree/palm section is hidden. When on, only rows with a price appear.',
                'price_per_tree' => $tree,
                'price_per_tree_label' => 'Price per tree',
                'price_per_tree_description' => 'Unit price for each tree. Customers choose quantity (number of trees) on the service product.',
                'price_per_tree_suffix' => 'AED / tree',
                'price_per_palm_tree' => $palm,
                'price_per_palm_tree_label' => 'Price per palm tree',
                'price_per_palm_tree_description' => 'Unit price for each palm. Customers choose quantity (number of palms) on the service product.',
                'price_per_palm_tree_suffix' => 'AED / palm',
                'currency' => 'AED',
                'optional_for_customer' => true,
            ],
        ];
    }

    /**
     * Customer product detail fields — only for services when admin enabled + priced rows.
     *
     * @return array<string, mixed>
     */
    public static function productApiFields(Product $product): array
    {
        if (! ServiceAreaPricing::appliesToProduct($product)) {
            return [
                'show_tree_options' => false,
                'tree_pricing' => null,
            ];
        }

        $config = self::globalConfig();
        $show = $config['show_tree_options'];
        $tree = $config['price_per_tree'];
        $palm = $config['price_per_palm_tree'];
        $treeRow = $show && $tree > 0;
        $palmRow = $show && $palm > 0;
        $enabled = $treeRow || $palmRow;

        return [
            'show_tree_options' => $enabled,
            'tree_pricing' => [
                'enabled' => $enabled,
                'optional' => true,
                'requires_quantity' => false,
                'note' => 'Optional. Skip to keep the normal/base service price. Enter quantity to add unit pricing.',
                'trees' => $treeRow ? [
                    'key' => 'tree_quantity',
                    'aliases' => ['trees', 'tree_count', 'number_of_trees'],
                    'label' => 'Number of trees',
                    'unit' => 'tree',
                    'unit_price' => $tree,
                    'unit_price_label' => ServiceAreaPricing::formatMoney($tree).' / tree',
                    'optional' => true,
                ] : null,
                'palm_trees' => $palmRow ? [
                    'key' => 'palm_tree_quantity',
                    'aliases' => ['palms', 'palm_trees', 'palm_count', 'number_of_palm_trees'],
                    'label' => 'Number of palm trees',
                    'unit' => 'palm',
                    'unit_price' => $palm,
                    'unit_price_label' => ServiceAreaPricing::formatMoney($palm).' / palm',
                    'optional' => true,
                ] : null,
            ],
        ];
    }

    public static function normalizeQuantity(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $qty = (int) round((float) $value);

        return $qty > 0 ? $qty : null;
    }

    /**
     * Resolve optional tree quantity from request aliases.
     */
    public static function resolveTreeQuantityFromRequest(Request $request): mixed
    {
        foreach ([
            'tree_quantity',
            'treeQuantity',
            'trees',
            'tree_count',
            'treeCount',
            'number_of_trees',
            'numberOfTrees',
        ] as $key) {
            if ($request->exists($key) && $request->input($key) !== null && $request->input($key) !== '') {
                return $request->input($key);
            }
        }

        foreach (['pricing', 'tree_pricing', 'service_pricing', 'data'] as $nestKey) {
            $nested = $request->input($nestKey);
            if (! is_array($nested)) {
                continue;
            }
            foreach (['tree_quantity', 'trees', 'tree_count', 'number_of_trees'] as $key) {
                if (array_key_exists($key, $nested) && $nested[$key] !== null && $nested[$key] !== '') {
                    return $nested[$key];
                }
            }
        }

        return null;
    }

    /**
     * Resolve optional palm quantity from request aliases.
     */
    public static function resolvePalmQuantityFromRequest(Request $request): mixed
    {
        foreach ([
            'palm_tree_quantity',
            'palmTreeQuantity',
            'palm_trees',
            'palmTrees',
            'palms',
            'palm_count',
            'palmCount',
            'number_of_palm_trees',
            'numberOfPalmTrees',
        ] as $key) {
            if ($request->exists($key) && $request->input($key) !== null && $request->input($key) !== '') {
                return $request->input($key);
            }
        }

        foreach (['pricing', 'tree_pricing', 'service_pricing', 'data'] as $nestKey) {
            $nested = $request->input($nestKey);
            if (! is_array($nested)) {
                continue;
            }
            foreach (['palm_tree_quantity', 'palm_trees', 'palms', 'palm_count', 'number_of_palm_trees'] as $key) {
                if (array_key_exists($key, $nested) && $nested[$key] !== null && $nested[$key] !== '') {
                    return $nested[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function resolveTreeQuantityFromArray(array $row): mixed
    {
        foreach (['tree_quantity', 'treeQuantity', 'trees', 'tree_count', 'number_of_trees'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function resolvePalmQuantityFromArray(array $row): mixed
    {
        foreach (['palm_tree_quantity', 'palmTreeQuantity', 'palm_trees', 'palms', 'palm_count', 'number_of_palm_trees'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * Validate optional quantities. Returns error message or null.
     */
    public static function validateQuantitiesMessage(Product $product, mixed $treeRaw, mixed $palmRaw): ?string
    {
        if (! ServiceAreaPricing::appliesToProduct($product)) {
            if (($treeRaw !== null && $treeRaw !== '') || ($palmRaw !== null && $palmRaw !== '')) {
                return 'Tree / palm quantities apply only to service products.';
            }

            return null;
        }

        if ($treeRaw !== null && $treeRaw !== '' && self::normalizeQuantity($treeRaw) === null) {
            return 'Number of trees must be a positive whole number when provided (e.g. 1, 2, 5). Send as tree_quantity (or trees).';
        }

        if ($palmRaw !== null && $palmRaw !== '' && self::normalizeQuantity($palmRaw) === null) {
            return 'Number of palm trees must be a positive whole number when provided (e.g. 1, 2, 5). Send as palm_tree_quantity (or palms).';
        }

        return null;
    }

    /**
     * Normalize customer quantities for a service line.
     * When the feature is off or a unit price is 0, that quantity is ignored (not required).
     *
     * @return array{tree_quantity: ?int, palm_tree_quantity: ?int, price_per_tree: float, price_per_palm_tree: float}
     */
    public static function resolveForCheckout(Product $product, mixed $treeRaw, mixed $palmRaw): array
    {
        $config = self::globalConfig();
        $empty = [
            'tree_quantity' => null,
            'palm_tree_quantity' => null,
            'price_per_tree' => 0.0,
            'price_per_palm_tree' => 0.0,
        ];

        if (! ServiceAreaPricing::appliesToProduct($product) || ! $config['show_tree_options']) {
            return $empty;
        }

        $treeQty = self::normalizeQuantity($treeRaw);
        $palmQty = self::normalizeQuantity($palmRaw);
        $treeRate = $config['price_per_tree'];
        $palmRate = $config['price_per_palm_tree'];

        if ($treeRate <= 0) {
            $treeQty = null;
        }
        if ($palmRate <= 0) {
            $palmQty = null;
        }

        return [
            'tree_quantity' => $treeQty,
            'palm_tree_quantity' => $palmQty,
            'price_per_tree' => $treeRate,
            'price_per_palm_tree' => $palmRate,
        ];
    }

    /**
     * Extra amount added on top of the normal/base service line total.
     */
    public static function addonTotal(?int $treeQuantity, ?int $palmTreeQuantity, ?float $pricePerTree = null, ?float $pricePerPalm = null): float
    {
        $config = self::globalConfig();
        $treeRate = $pricePerTree !== null ? max(0, round($pricePerTree, 2)) : $config['price_per_tree'];
        $palmRate = $pricePerPalm !== null ? max(0, round($pricePerPalm, 2)) : $config['price_per_palm_tree'];

        $total = 0.0;
        if ($treeQuantity !== null && $treeQuantity > 0 && $treeRate > 0) {
            $total += $treeQuantity * $treeRate;
        }
        if ($palmTreeQuantity !== null && $palmTreeQuantity > 0 && $palmRate > 0) {
            $total += $palmTreeQuantity * $palmRate;
        }

        return round($total, 2);
    }

    /**
     * Cart / order line API fields for tree/palm quantities.
     *
     * @return array<string, mixed>
     */
    public static function lineApiFields(
        Product $product,
        ?int $treeQuantity,
        ?int $palmTreeQuantity,
        ?float $pricePerTree = null,
        ?float $pricePerPalm = null
    ): array {
        $config = self::globalConfig();
        $treeRate = $pricePerTree !== null ? max(0, round($pricePerTree, 2)) : $config['price_per_tree'];
        $palmRate = $pricePerPalm !== null ? max(0, round($pricePerPalm, 2)) : $config['price_per_palm_tree'];
        $addon = self::addonTotal($treeQuantity, $palmTreeQuantity, $treeRate, $palmRate);
        $enabled = self::isEnabledForProduct($product);

        $breakdown = null;
        if ($addon > 0) {
            $parts = [];
            if ($treeQuantity !== null && $treeQuantity > 0 && $treeRate > 0) {
                $parts[] = $treeQuantity.' × '.$treeRate;
            }
            if ($palmTreeQuantity !== null && $palmTreeQuantity > 0 && $palmRate > 0) {
                $parts[] = $palmTreeQuantity.' × '.$palmRate;
            }
            $breakdown = [
                'tree_quantity' => $treeQuantity,
                'palm_tree_quantity' => $palmTreeQuantity,
                'price_per_tree' => $treeRate > 0 ? $treeRate : null,
                'price_per_palm_tree' => $palmRate > 0 ? $palmRate : null,
                'addon_total' => $addon,
                'addon_total_label' => ServiceAreaPricing::formatMoney($addon),
                'formula' => implode(' + ', $parts).' = '.$addon,
            ];
        }

        return [
            'show_tree_options' => $enabled,
            'tree_quantity' => $enabled ? $treeQuantity : null,
            'palm_tree_quantity' => $enabled ? $palmTreeQuantity : null,
            'price_per_tree' => $enabled && $treeRate > 0 ? $treeRate : null,
            'price_per_palm_tree' => $enabled && $palmRate > 0 ? $palmRate : null,
            'tree_palm_addon' => $addon,
            'tree_palm_addon_label' => ServiceAreaPricing::formatMoney($addon),
            'tree_pricing_breakdown' => $breakdown,
        ];
    }

    /**
     * Normalize admin form-data for tree pricing fields onto the request.
     */
    public static function normalizeAdminFormData(Request $request): void
    {
        foreach ([
            'show_tree_options',
            'show_on_customer_service_products',
            'showTreeOptions',
        ] as $key) {
            if ($request->has($key)) {
                $request->merge([
                    'show_tree_options' => filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN),
                ]);
                break;
            }
        }

        foreach (['price_per_tree', 'pricePerTree'] as $key) {
            if ($request->has($key) && $request->input($key) !== null && $request->input($key) !== '') {
                $request->merge(['price_per_tree' => $request->input($key)]);
                break;
            }
        }

        foreach (['price_per_palm_tree', 'pricePerPalmTree', 'price_per_palm'] as $key) {
            if ($request->has($key) && $request->input($key) !== null && $request->input($key) !== '') {
                $request->merge(['price_per_palm_tree' => $request->input($key)]);
                break;
            }
        }

        $nested = $request->input('tree_pricing');
        if (is_string($nested)) {
            $decoded = json_decode($nested, true);
            $nested = is_array($decoded) ? $decoded : null;
        }
        if (is_array($nested)) {
            if (array_key_exists('show', $nested) && ! $request->has('show_tree_options')) {
                $request->merge(['show_tree_options' => filter_var($nested['show'], FILTER_VALIDATE_BOOLEAN)]);
            }
            if (array_key_exists('price_per_tree', $nested) && ! $request->has('price_per_tree')) {
                $request->merge(['price_per_tree' => $nested['price_per_tree']]);
            }
            if (array_key_exists('price_per_palm_tree', $nested) && ! $request->has('price_per_palm_tree')) {
                $request->merge(['price_per_palm_tree' => $nested['price_per_palm_tree']]);
            }
        }
    }

    /**
     * @return array{show_tree_options: bool, price_per_tree: float, price_per_palm_tree: float}|null
     */
    public static function validatedFromRequest(Request $request): ?array
    {
        $hasAny = $request->has('show_tree_options')
            || $request->has('price_per_tree')
            || $request->has('price_per_palm_tree')
            || $request->has('tree_pricing');

        if (! $hasAny) {
            return null;
        }

        $current = self::globalConfig();

        return [
            'show_tree_options' => $request->has('show_tree_options')
                ? (bool) $request->boolean('show_tree_options')
                : $current['show_tree_options'],
            'price_per_tree' => $request->has('price_per_tree')
                ? max(0, round((float) $request->input('price_per_tree'), 2))
                : $current['price_per_tree'],
            'price_per_palm_tree' => $request->has('price_per_palm_tree')
                ? max(0, round((float) $request->input('price_per_palm_tree'), 2))
                : $current['price_per_palm_tree'],
        ];
    }
}
