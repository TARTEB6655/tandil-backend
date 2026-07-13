<?php

namespace App\Services\Vendor;

use App\Enums\VendorStatus;
use App\Models\Product;
use App\Models\VendorProduct;
use Illuminate\Support\Collection;

class VendorComparisonService
{
    /**
     * Compare live vendor listings in the same admin category as the reference product.
     *
     * @return array<string, mixed>
     */
    public function compareByProduct(int $productId, string $sortBy = 'price'): array
    {
        $product = Product::with('category')->findOrFail($productId);
        $productIds = $this->comparableProductIdsForProduct($product);

        return $this->buildComparison($productIds, $product, $sortBy);
    }

    /**
     * @param  list<int>|Collection<int, int>  $productIds
     * @return array<string, mixed>
     */
    public function compareProducts(array|Collection $productIds, string $sortBy = 'price'): array
    {
        $ids = $productIds instanceof Collection ? $productIds : collect($productIds);
        $reference = Product::with('category')->find($ids->first());

        return $this->buildComparison($ids, $reference, $sortBy);
    }

    /**
     * Metadata for product detail — show "Compare vendors & prices" when 2+ vendors exist in category.
     *
     * @return array{available: bool, vendor_count: int, label: string}
     */
    public function availabilityForProduct(Product $product): array
    {
        $vendorCount = $this->distinctVendorCountForProduct($product);

        return [
            'available' => $vendorCount >= 2,
            'vendor_count' => $vendorCount,
            'label' => 'Compare vendors & prices',
        ];
    }

    /**
     * @return Collection<int, int>
     */
    public function comparableProductIdsForProduct(Product $product): Collection
    {
        if (! $product->category_id) {
            return collect();
        }

        return Product::query()
            ->visibleInClientShop()
            ->where('category_id', $product->category_id)
            ->whereHas('vendorProduct')
            ->pluck('id');
    }

    public function distinctVendorCountForProduct(Product $product): int
    {
        $productIds = $this->comparableProductIdsForProduct($product);
        if ($productIds->isEmpty()) {
            return 0;
        }

        return (int) VendorProduct::query()
            ->whereIn('product_id', $productIds)
            ->marketplaceLive()
            ->whereHas('vendor', fn ($q) => $q->where('status', VendorStatus::Approved->value))
            ->distinct()
            ->count('vendor_id');
    }

    /**
     * @param  Collection<int, int>|list<int>  $productIds
     */
    private function buildComparison(Collection|array $productIds, ?Product $reference, string $sortBy): array
    {
        $ids = $productIds instanceof Collection ? $productIds : collect($productIds);
        $sortBy = $this->normalizeSortBy($sortBy);

        $vendorProducts = VendorProduct::with([
            'vendor.profile',
            'product.category',
            'inventory',
            'currentPrice',
        ])
            ->whereIn('product_id', $ids)
            ->marketplaceLive()
            ->whereHas('vendor', fn ($q) => $q->where('status', VendorStatus::Approved->value))
            ->get();

        $rows = $vendorProducts->map(function (VendorProduct $vp) {
            $vendor = $vp->vendor;
            $product = $vp->product;
            $price = $vp->currentPrice?->price ?? $product?->price;
            $price = $price !== null ? (float) $price : null;
            $originalPrice = $vp->currentPrice?->compare_at_price ?? $product?->compare_at_price;
            $originalPrice = $originalPrice !== null ? (float) $originalPrice : null;
            $qty = $vp->stockQuantity();
            $currency = $vp->currentPrice?->currency ?? 'AED';
            $deliveryDays = $this->resolveDeliveryDays(
                $product?->estimated_arrival,
                $vendor->profile?->delivery_radius
            );

            return [
                'vendor_product_id' => $vp->id,
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->profile?->business_name,
                'vendor_logo_url' => $vendor->logo_url,
                'product_id' => $product?->id,
                'product_name' => $product?->name,
                'price' => $price,
                'price_formatted' => $price !== null ? $currency.' '.number_format($price, 2) : null,
                'compare_at_price' => $originalPrice,
                'original_price' => $originalPrice,
                'discount_percent' => $this->discountPercent($price, $originalPrice),
                'currency' => $currency,
                'in_stock' => $qty > 0,
                'stock_quantity' => (int) $qty,
                'stock_label' => $qty > 0 ? $qty.' in stock' : 'Out of stock',
                'delivery_days' => $deliveryDays,
                'delivery_label' => $deliveryDays.' day delivery',
                'delivery_info' => $deliveryDays.' day delivery',
                'rating' => null,
                'rating_available' => false,
                'is_best_price' => false,
            ];
        });

        $rows = $this->applySort($rows, $sortBy);
        $rows = $this->markBestPrice($rows);

        $vendorCount = $rows->pluck('vendor_id')->unique()->count();

        return [
            'reference_product' => $reference ? [
                'id' => $reference->id,
                'name' => $reference->name,
                'category_id' => $reference->category_id,
                'category_name' => $reference->category?->name,
                'category' => $reference->category?->name,
            ] : null,
            'sort_by' => $sortBy,
            'compare_available' => $vendorCount >= 2,
            'vendor_count' => $vendorCount,
            'vendors' => $rows->values()->all(),
            'items' => $rows->values()->all(),
            'count' => $rows->count(),
        ];
    }

    private function normalizeSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['price', 'rating', 'delivery'], true) ? $sortBy : 'price';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function applySort(Collection $rows, string $sortBy): Collection
    {
        return match ($sortBy) {
            'rating' => $rows->sortByDesc(fn (array $row) => $row['rating'] ?? -1)->values(),
            'delivery' => $rows->sortBy(fn (array $row) => $row['delivery_days'] ?? PHP_INT_MAX)->values(),
            default => $rows->sortBy(fn (array $row) => $row['price'] ?? PHP_FLOAT_MAX)->values(),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function markBestPrice(Collection $rows): Collection
    {
        $pricedInStock = $rows
            ->filter(fn (array $row) => $row['in_stock'] && $row['price'] !== null);

        if ($pricedInStock->isEmpty()) {
            return $rows;
        }

        $bestPrice = $pricedInStock->min('price');
        $bestMarked = false;

        return $rows->map(function (array $row) use ($bestPrice, &$bestMarked) {
            if (! $bestMarked && $row['in_stock'] && $row['price'] === $bestPrice) {
                $row['is_best_price'] = true;
                $bestMarked = true;
            }

            return $row;
        });
    }

    private function discountPercent(?float $price, ?float $originalPrice): ?int
    {
        if ($price === null || $originalPrice === null || $originalPrice <= $price) {
            return null;
        }

        return (int) round((($originalPrice - $price) / $originalPrice) * 100);
    }

    private function resolveDeliveryDays(?string $estimatedArrival, mixed $deliveryRadius): int
    {
        if (is_string($estimatedArrival) && $estimatedArrival !== '') {
            if (preg_match('/(\d+)\s*(?:-\s*(\d+))?\s*day/i', $estimatedArrival, $matches)) {
                return max(1, (int) ($matches[2] ?? $matches[1]));
            }
            if (preg_match('/(\d+)/', $estimatedArrival, $matches)) {
                return max(1, (int) $matches[1]);
            }
        }

        $radius = $deliveryRadius !== null ? (float) $deliveryRadius : null;
        if ($radius !== null) {
            if ($radius <= 10) {
                return 1;
            }
            if ($radius <= 25) {
                return 2;
            }

            return 3;
        }

        return 2;
    }
}
