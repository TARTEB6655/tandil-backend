<?php

namespace App\Services\Vendor;

use App\Enums\VendorStatus;
use App\Models\Product;
use App\Models\VendorProduct;
use Illuminate\Support\Collection;

class VendorComparisonService
{
    /**
     * Compare vendors offering products with similar name in same category.
     *
     * @return array<string, mixed>
     */
    public function compareByProduct(int $productId): array
    {
        $product = Product::with('category')->findOrFail($productId);
        $categoryId = $product->category_id;
        $nameLike = '%'.substr($product->name, 0, min(20, strlen($product->name))).'%';

        $similarProductIds = Product::query()
            ->where('status', 'active')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->where('name', 'like', $nameLike)
            ->whereNotNull('vendor_id')
            ->pluck('id');

        return $this->buildComparison($similarProductIds, $product);
    }

    /**
     * @param  list<int>|Collection<int, int>  $productIds
     * @return array<string, mixed>
     */
    public function compareProducts(array|Collection $productIds): array
    {
        $ids = $productIds instanceof Collection ? $productIds : collect($productIds);
        $reference = Product::find($ids->first());

        return $this->buildComparison($ids, $reference);
    }

    /**
     * @param  Collection<int, int>|list<int>  $productIds
     */
    private function buildComparison(Collection|array $productIds, ?Product $reference): array
    {
        $ids = $productIds instanceof Collection ? $productIds : collect($productIds);

        $vendorProducts = VendorProduct::with([
            'vendor.profile',
            'product.category',
            'inventory',
            'currentPrice',
        ])
            ->whereIn('product_id', $ids)
            ->whereHas('vendor', fn ($q) => $q->where('status', VendorStatus::Approved->value))
            ->where('status', 'active')
            ->get();

        $rows = $vendorProducts->map(function (VendorProduct $vp) {
            $vendor = $vp->vendor;
            $product = $vp->product;
            $price = $vp->currentPrice?->price ?? $product?->price;
            $qty = $vp->inventory?->quantity ?? $product?->stock ?? 0;

            return [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->profile?->business_name,
                'vendor_logo_url' => $vendor->logo_url,
                'product_id' => $product?->id,
                'product_name' => $product?->name,
                'price' => $price !== null ? (float) $price : null,
                'compare_at_price' => $vp->currentPrice?->compare_at_price ?? $product?->compare_at_price,
                'currency' => $vp->currentPrice?->currency ?? 'AED',
                'in_stock' => $qty > 0,
                'stock_quantity' => (int) $qty,
                'delivery_info' => $product?->requires_shipping
                    ? 'Shipping required'
                    : 'Digital / no shipping',
                'rating' => null,
                'rating_available' => false,
            ];
        })->sortBy('price')->values();

        return [
            'reference_product' => $reference ? [
                'id' => $reference->id,
                'name' => $reference->name,
                'category' => $reference->category?->name,
            ] : null,
            'vendors' => $rows,
            'count' => $rows->count(),
        ];
    }
}
