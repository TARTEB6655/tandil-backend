<?php

namespace App\Services\Shop;

use App\Enums\VendorStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VendorStoreService
{
    public function findVisibleVendor(int $vendorId): ?Vendor
    {
        return Vendor::query()
            ->approved()
            ->with('profile')
            ->where('id', $vendorId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function vendorPayload(Vendor $vendor, ?int $productCount = null): array
    {
        $profile = $vendor->profile;
        $productCount ??= $this->visibleProductQuery($vendor->id)->count();

        return [
            'id' => $vendor->id,
            'business_name' => $profile?->business_name,
            'logo_url' => $vendor->logo_url,
            'store_description' => $profile?->description,
            'phone' => $profile?->phone,
            'city' => $profile?->city,
            'address' => $profile?->address,
            'operating_hours' => $profile?->operating_hours,
            'delivery_radius_km' => $profile?->delivery_radius !== null
                ? (float) $profile->delivery_radius
                : null,
            'minimum_order_amount' => $profile?->minimum_order_amount !== null
                ? (float) $profile->minimum_order_amount
                : null,
            'rating' => null,
            'rating_available' => false,
            'product_count' => $productCount,
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    public function listProducts(Vendor $vendor, array $filters = []): Collection
    {
        return $this->buildProductQuery($vendor, $filters)->get();
    }

    private function buildProductQuery(Vendor $vendor, array $filters = []): Builder
    {
        $query = $this->visibleProductQuery($vendor->id)
            ->with(['category', 'images', 'primaryImage', 'vendorProduct.currentPrice', 'vendorProduct.inventory']);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortDir = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'price') {
            $query->orderBy('price', $sortDir);
        } elseif ($sortBy === 'name') {
            $query->orderBy('name', $sortDir);
        } else {
            $query->ordered();
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function productCard(Product $product): array
    {
        $product->loadMissing(['category', 'images', 'primaryImage', 'vendorProduct.currentPrice', 'vendorProduct.inventory']);

        $imagePath = $product->primaryImage?->image_path ?? $product->image;
        $price = $product->vendorProduct?->currentPrice?->price ?? $product->price;
        $compareAt = $product->vendorProduct?->currentPrice?->compare_at_price ?? $product->compare_at_price;
        $qty = $product->vendorProduct?->stockQuantity() ?? (int) $product->stock;

        return [
            'product_id' => $product->id,
            'vendor_product_id' => $product->vendorProduct?->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $price !== null ? (float) $price : null,
            'compare_at_price' => $compareAt !== null ? (float) $compareAt : null,
            'currency' => $product->vendorProduct?->currentPrice?->currency ?? 'AED',
            'image_url' => ProductImage::buildFullUrl($imagePath),
            'in_stock' => $qty > 0,
            'stock_quantity' => (int) $qty,
            'stock_label' => $qty > 0 ? $qty.' in stock' : 'Out of stock',
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
        ];
    }

    private function visibleProductQuery(int $vendorId): Builder
    {
        return Product::query()
            ->visibleInClientShop()
            ->where('vendor_id', $vendorId)
            ->whereHas('vendorProduct', fn ($q) => $q
                ->where('vendor_id', $vendorId)
                ->marketplaceLive()
                ->whereHas('vendor', fn ($vendor) => $vendor->where('status', VendorStatus::Approved->value)));
    }
}
