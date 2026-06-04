<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;

/**
 * Per-category tax on the merchandise subtotal (after discounts, allocated by category share).
 */
final class CategoryTaxService
{
    /**
     * @param  iterable<int, Cart|array<string, mixed>>  $items
     * @return array{
     *   amount: float,
     *   effective_percent: float,
     *   breakdown: array<int, array{category_id: ?int, category_name: string, tax_percentage: float, taxable_amount: float, tax_amount: float}>,
     *   uses_category_tax: bool
     * }
     */
    public static function resolveForCartItems(iterable $items, float $taxableMerchandiseTotal): array
    {
        $taxableMerchandiseTotal = round(max(0, $taxableMerchandiseTotal), 2);
        if ($taxableMerchandiseTotal <= 0) {
            return [
                'amount' => 0.0,
                'effective_percent' => 0.0,
                'breakdown' => [],
                'uses_category_tax' => false,
            ];
        }

        $byCategory = [];
        $uncategorizedSubtotal = 0.0;

        foreach ($items as $item) {
            $product = self::productFromLine($item);
            if ($product === null) {
                continue;
            }
            $qty = self::quantityFromLine($item);
            $unit = self::unitPriceFromLine($item, $product);
            $lineTotal = round($qty * $unit, 2);
            if ($lineTotal <= 0) {
                continue;
            }
            if ($product->category_id) {
                $catId = (int) $product->category_id;
                $byCategory[$catId] = ($byCategory[$catId] ?? 0.0) + $lineTotal;
            } else {
                $uncategorizedSubtotal += $lineTotal;
            }
        }

        $merchandiseSubtotal = array_sum($byCategory) + $uncategorizedSubtotal;
        if ($merchandiseSubtotal <= 0) {
            $global = CartController::getEffectiveTaxPercent();

            return [
                'amount' => round($taxableMerchandiseTotal * ($global / 100), 2),
                'effective_percent' => $global,
                'breakdown' => [],
                'uses_category_tax' => false,
            ];
        }

        $categories = $byCategory !== []
            ? Category::query()->whereIn('id', array_keys($byCategory))->get()->keyBy('id')
            : collect();

        $breakdown = [];
        $taxTotal = 0.0;
        $usesCategoryTax = false;

        foreach ($byCategory as $catId => $categorySubtotal) {
            $category = $categories->get($catId);
            $share = $categorySubtotal / $merchandiseSubtotal;
            $categoryTaxable = round($taxableMerchandiseTotal * $share, 2);
            $rate = $category !== null && $category->tax_percentage !== null
                ? round((float) $category->tax_percentage, 2)
                : CartController::getEffectiveTaxPercent();
            if ($category !== null && $category->tax_percentage !== null) {
                $usesCategoryTax = true;
            }
            $lineTax = round($categoryTaxable * ($rate / 100), 2);
            $taxTotal += $lineTax;
            $breakdown[] = [
                'category_id' => $catId,
                'category_name' => $category?->name ?? 'Category',
                'tax_percentage' => $rate,
                'taxable_amount' => $categoryTaxable,
                'tax_amount' => $lineTax,
            ];
        }

        if ($uncategorizedSubtotal > 0) {
            $share = $uncategorizedSubtotal / $merchandiseSubtotal;
            $categoryTaxable = round($taxableMerchandiseTotal * $share, 2);
            $rate = CartController::getEffectiveTaxPercent();
            $lineTax = round($categoryTaxable * ($rate / 100), 2);
            $taxTotal += $lineTax;
            $breakdown[] = [
                'category_id' => null,
                'category_name' => 'Other',
                'tax_percentage' => $rate,
                'taxable_amount' => $categoryTaxable,
                'tax_amount' => $lineTax,
            ];
        }

        $taxTotal = round($taxTotal, 2);
        $effectivePercent = $taxableMerchandiseTotal > 0
            ? round($taxTotal / $taxableMerchandiseTotal * 100, 2)
            : 0.0;

        return [
            'amount' => $taxTotal,
            'effective_percent' => $effectivePercent,
            'breakdown' => $breakdown,
            'uses_category_tax' => $usesCategoryTax,
        ];
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
            return Product::query()->with('category')->find((int) $productId);
        }

        return null;
    }

    /**
     * @param  Cart|array<string, mixed>  $item
     */
    private static function quantityFromLine(mixed $item): int
    {
        if ($item instanceof Cart) {
            return max(1, (int) $item->quantity);
        }
        if (is_array($item)) {
            return max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
        }

        return 1;
    }

    /**
     * @param  Cart|array<string, mixed>  $item
     */
    private static function unitPriceFromLine(mixed $item, Product $product): float
    {
        if ($item instanceof Cart) {
            return (float) $item->lineUnitPrice();
        }
        if (is_array($item) && isset($item['unit_price'])) {
            return (float) $item['unit_price'];
        }

        return (float) $product->price;
    }
}
