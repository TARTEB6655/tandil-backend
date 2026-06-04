<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;

/**
 * Per-category delivery fees (e.g. small items / bike vs large items / car).
 * Checkout sums one fee per distinct category in the basket; unset categories use the global shop default once.
 */
final class CategoryShippingService
{
    /**
     * @param  iterable<int, Cart|array<string, mixed>>  $items  Cart lines or arrays with product_id / product
     * @return array{
     *   amount: float,
     *   breakdown: array<int, array{category_id: ?int, category_name: string, shipping_amount: float, source: string}>,
     *   uses_category_rates: bool
     * }
     */
    public static function resolveForCartItems(iterable $items): array
    {
        $categoryIds = [];
        $hasUncategorizedProduct = false;

        foreach ($items as $item) {
            $product = self::productFromLine($item);
            if ($product === null) {
                continue;
            }
            if ($product->category_id) {
                $categoryIds[(int) $product->category_id] = true;
            } else {
                $hasUncategorizedProduct = true;
            }
        }

        if ($categoryIds === [] && ! $hasUncategorizedProduct) {
            return [
                'amount' => 0.0,
                'breakdown' => [],
                'uses_category_rates' => false,
            ];
        }

        $global = CartController::getEffectiveShippingAmount();
        $categories = Category::query()
            ->whereIn('id', array_keys($categoryIds))
            ->get()
            ->keyBy('id');

        $breakdown = [];
        $total = 0.0;
        $usesCategoryRates = false;
        $needsGlobalOnce = $hasUncategorizedProduct;

        foreach (array_keys($categoryIds) as $catId) {
            $category = $categories->get($catId);
            $configured = $category !== null && $category->shipping_cost !== null;

            if ($configured) {
                $usesCategoryRates = true;
                $amount = round((float) $category->shipping_cost, 2);
                $breakdown[] = self::breakdownLine(
                    $catId,
                    $category->name,
                    $amount,
                    'category',
                    $category->shipping_type
                );
                $total += $amount;
            } else {
                $needsGlobalOnce = true;
            }
        }

        if ($needsGlobalOnce) {
            $breakdown[] = self::breakdownLine(
                null,
                'Standard delivery',
                round($global, 2),
                'global_default',
                null
            );
            $total += $global;
        }

        return [
            'amount' => round($total, 2),
            'breakdown' => $breakdown,
            'uses_category_rates' => $usesCategoryRates,
        ];
    }

    public static function shippingAmountForCategoryId(?int $categoryId): ?float
    {
        if ($categoryId === null) {
            return null;
        }

        $category = Category::query()->find($categoryId);
        if ($category === null || $category->shipping_cost === null) {
            return null;
        }

        return round((float) $category->shipping_cost, 2);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allCategoryRatesForAdmin(): array
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'shipping_cost', 'shipping_type', 'tax_percentage'])
            ->map(fn (Category $c) => self::rateRowForCategory($c))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rateRowForCategory(Category $c): array
    {
        $row = $c->shippingTaxConfigForApi();
        $row['category_id'] = (int) $c->id;
        $row['category_name'] = $c->name;

        return $row;
    }

    /**
     * @return array{category_id: ?int, category_name: string, shipping_amount: float, source: string, delivery_type: ?string, delivery_type_label: string}
     */
    private static function breakdownLine(?int $categoryId, string $name, float $amount, string $source, ?string $deliveryType): array
    {
        return [
            'category_id' => $categoryId,
            'category_name' => $name,
            'shipping_amount' => $amount,
            'shipping_cost' => $amount,
            'source' => $source,
            'shipping_type' => $deliveryType,
            'delivery_type' => $deliveryType,
            'delivery_type_label' => Category::shippingTypeLabel($deliveryType),
            'shipping_type_label' => Category::shippingTypeLabel($deliveryType),
        ];
    }

    /**
     * @param  array<int, array{category_id: int, shipping_amount?: float|null}>  $rates
     */
    public static function syncAdminRates(array $rates): void
    {
        foreach ($rates as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $category = Category::query()->find($categoryId);
            if ($category === null) {
                continue;
            }

            $costRaw = $row['shipping_cost'] ?? $row['shipping_amount'] ?? null;
            if ($costRaw === null || $costRaw === '') {
                $category->shipping_cost = null;
            } else {
                $category->shipping_cost = round(max(0, (float) $costRaw), 2);
            }
            $typeRaw = $row['shipping_type'] ?? $row['delivery_type'] ?? null;
            if (array_key_exists('shipping_type', $row) || array_key_exists('delivery_type', $row)) {
                $category->shipping_type = Category::normalizeShippingType($typeRaw);
            }
            if (array_key_exists('tax_percentage', $row)) {
                $tax = $row['tax_percentage'];
                $category->tax_percentage = ($tax === null || $tax === '')
                    ? null
                    : round(max(0, min(100, (float) $tax)), 2);
            }
            $category->save();
        }
    }

    /**
     * @param  Cart|array<string, mixed>  $item
     */
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
