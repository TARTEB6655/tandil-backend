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
            $configured = $category !== null && $category->shipping_amount !== null;

            if ($configured) {
                $usesCategoryRates = true;
                $amount = round((float) $category->shipping_amount, 2);
                $breakdown[] = [
                    'category_id' => $catId,
                    'category_name' => $category->name,
                    'shipping_amount' => $amount,
                    'source' => 'category',
                ];
                $total += $amount;
            } else {
                $needsGlobalOnce = true;
            }
        }

        if ($needsGlobalOnce) {
            $breakdown[] = [
                'category_id' => null,
                'category_name' => 'Standard delivery',
                'shipping_amount' => round($global, 2),
                'source' => 'global_default',
            ];
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
        if ($category === null || $category->shipping_amount === null) {
            return null;
        }

        return round((float) $category->shipping_amount, 2);
    }

    /**
     * @return array<int, array{category_id: int, category_name: string, shipping_amount: ?float}>
     */
    public static function allCategoryRatesForAdmin(): array
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'shipping_amount'])
            ->map(fn (Category $c) => [
                'category_id' => (int) $c->id,
                'category_name' => $c->name,
                'shipping_amount' => $c->shipping_amount !== null ? round((float) $c->shipping_amount, 2) : null,
            ])
            ->values()
            ->all();
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

            if (! array_key_exists('shipping_amount', $row) || $row['shipping_amount'] === null || $row['shipping_amount'] === '') {
                $category->shipping_amount = null;
            } else {
                $category->shipping_amount = round(max(0, (float) $row['shipping_amount']), 2);
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
