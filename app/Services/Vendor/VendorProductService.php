<?php

namespace App\Services\Vendor;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VendorProductService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Vendor $vendor, array $data, ?UploadedFile $image = null): VendorProduct
    {
        return DB::transaction(function () use ($vendor, $data, $image) {
            $imagePath = $image ? $image->store('products', 'public') : null;

            $approvalStatus = AdminVendorProductService::initialApprovalStatus();
            $productActive = $approvalStatus === 'approved' ? ($data['status'] ?? 'active') : 'draft';

            $categoryId = $data['category_id'] ?? Category::query()->value('id');

            $product = Product::create([
                'vendor_id' => $vendor->id,
                'category_id' => $categoryId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'stock' => $data['stock'] ?? 0,
                'status' => $productActive,
                'product_type' => $data['product_type'] ?? Product::TYPE_SIMPLE,
                'sku' => $data['sku'] ?? null,
                'image' => $imagePath,
                'track_quantity' => true,
            ]);

            $vendorProduct = VendorProduct::create([
                'vendor_id' => $vendor->id,
                'product_id' => $product->id,
                'status' => $data['vendor_product_status'] ?? ($approvalStatus === 'approved' ? 'active' : 'inactive'),
                'approval_status' => $approvalStatus,
            ]);

            $this->recordPrice($vendorProduct, (float) $data['price'], isset($data['compare_at_price']) ? (float) $data['compare_at_price'] : null, $vendor->user_id, false);

            VendorInventory::create([
                'vendor_product_id' => $vendorProduct->id,
                'quantity' => (int) ($data['stock'] ?? 0),
                'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? 5),
            ]);

            return $vendorProduct->load(['product.category', 'inventory', 'currentPrice']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(VendorProduct $vendorProduct, array $data, ?UploadedFile $image = null, bool $adminOverride = false, ?int $setByUserId = null): VendorProduct
    {
        return DB::transaction(function () use ($vendorProduct, $data, $image, $adminOverride, $setByUserId) {
            $product = $vendorProduct->product;

            $productUpdates = array_filter([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? null,
                'sku' => $data['sku'] ?? null,
            ], fn ($v) => $v !== null);

            if ($image) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $productUpdates['image'] = $image->store('products', 'public');
            }

            if (isset($data['price'])) {
                $productUpdates['price'] = $data['price'];
                $this->recordPrice(
                    $vendorProduct,
                    (float) $data['price'],
                    isset($data['compare_at_price']) ? (float) $data['compare_at_price'] : null,
                    $setByUserId,
                    $adminOverride
                );
            }

            if ($productUpdates !== []) {
                $product->update($productUpdates);
            }

            if (isset($data['vendor_product_status'])) {
                $vendorProduct->update(['status' => $data['vendor_product_status']]);
            }

            if (isset($data['stock']) || isset($data['low_stock_threshold'])) {
                $inv = $vendorProduct->inventory ?? VendorInventory::create(['vendor_product_id' => $vendorProduct->id]);
                $inv->update(array_filter([
                    'quantity' => isset($data['stock']) ? (int) $data['stock'] : null,
                    'low_stock_threshold' => isset($data['low_stock_threshold']) ? (int) $data['low_stock_threshold'] : null,
                ], fn ($v) => $v !== null));
                $product->update(['stock' => $inv->quantity]);
            }

            return $vendorProduct->fresh(['product.category', 'inventory', 'currentPrice']);
        });
    }

    public function delete(VendorProduct $vendorProduct): void
    {
        DB::transaction(function () use ($vendorProduct) {
            $vendorProduct->delete();
            $vendorProduct->product?->update(['status' => 'archived']);
        });
    }

    private function recordPrice(VendorProduct $vp, float $price, ?float $compareAt, ?int $userId, bool $adminOverride): void
    {
        VendorProductPrice::where('vendor_product_id', $vp->id)
            ->whereNull('effective_to')
            ->update(['effective_to' => now()]);

        VendorProductPrice::create([
            'vendor_product_id' => $vp->id,
            'price' => $price,
            'compare_at_price' => $compareAt,
            'set_by_user_id' => $userId,
            'is_admin_override' => $adminOverride,
            'effective_from' => now(),
        ]);
    }

    public function findForVendor(Vendor $vendor, int $vendorProductId): ?VendorProduct
    {
        return VendorProduct::with(['product.category', 'inventory', 'currentPrice'])
            ->where('vendor_id', $vendor->id)
            ->where('id', $vendorProductId)
            ->first();
    }
}
