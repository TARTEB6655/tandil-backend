<?php

namespace App\Services\Vendor;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use App\Services\Product\ProductCatalogWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorProductService
{
    public function __construct(
        private readonly ProductCatalogWriter $catalog
    ) {}

    public function createFromRequest(Vendor $vendor, Request $request): VendorProduct
    {
        $this->catalog->prepareRequest($request);
        $validated = $request->validate(
            $this->catalog->storeRules(),
            $this->catalog->validationMessages()
        );

        return DB::transaction(function () use ($vendor, $request, $validated) {
            $approvalStatus = AdminVendorProductService::initialApprovalStatus();
            $productActive = $approvalStatus === 'approved' ? ($validated['status'] ?? 'active') : 'draft';

            $createData = $this->catalog->buildCreateData($request, $validated, [
                'vendor_id' => $vendor->id,
                'status' => $productActive,
                'track_quantity' => true,
            ]);

            $categoryId = $this->catalog->resolveCategoryId($request, $validated);
            $this->catalog->assertCategoryAllowed(
                $categoryId,
                fn (int $id) => Category::vendorAssignable()->where('id', $id)->exists()
            );
            $createData['category_id'] = $categoryId;

            try {
                $product = Product::create($createData);
            } catch (\Illuminate\Database\QueryException $e) {
                $this->rethrowUniqueProductErrors($e, $createData);
            }

            $vendorProduct = VendorProduct::create([
                'vendor_id' => $vendor->id,
                'product_id' => $product->id,
                'status' => $validated['vendor_product_status'] ?? ($approvalStatus === 'approved' ? 'active' : 'inactive'),
                'approval_status' => $approvalStatus,
            ]);

            $this->recordPrice(
                $vendorProduct,
                (float) $validated['price'],
                null,
                $vendor->user_id,
                false
            );

            VendorInventory::create([
                'vendor_product_id' => $vendorProduct->id,
                'quantity' => (int) ($validated['stock'] ?? 0),
                'low_stock_threshold' => 5,
            ]);

            $this->catalog->persistImages($product, $request);
            $this->syncServicesFromRequest($product, $request);
            $this->catalog->persistOptionGroups($product, $request);

            return $vendorProduct->load([
                'product.category',
                'product.services',
                'product.images',
                'product.primaryImage',
                'product.optionGroups.options',
                'product.variants.options',
                'inventory',
                'currentPrice',
            ]);
        });
    }

    public function updateFromRequest(VendorProduct $vendorProduct, Request $request, ?int $setByUserId = null): VendorProduct
    {
        $this->catalog->prepareRequest($request);
        $productId = $vendorProduct->product_id;
        $validated = $request->validate(
            $this->catalog->storeRules($productId),
            $this->catalog->validationMessages()
        );

        return DB::transaction(function () use ($vendorProduct, $request, $validated, $setByUserId) {
            $product = $vendorProduct->product;

            $updateData = $this->catalog->buildUpdateData($request, $validated);
            if (array_key_exists('category_id', $updateData) && $updateData['category_id'] !== null) {
                $this->catalog->assertCategoryAllowed(
                    (int) $updateData['category_id'],
                    fn (int $id) => Category::vendorAssignable()->where('id', $id)->exists()
                );
            }

            if (isset($validated['price'])) {
                $updateData['price'] = $validated['price'];
                $this->recordPrice(
                    $vendorProduct,
                    (float) $validated['price'],
                    null,
                    $setByUserId,
                    false
                );
            }

            if ($updateData !== []) {
                try {
                    $product->update($updateData);
                } catch (\Illuminate\Database\QueryException $e) {
                    $this->rethrowUniqueProductErrors($e, $updateData);
                }
            }

            if (isset($validated['vendor_product_status'])) {
                $vendorProduct->update(['status' => $validated['vendor_product_status']]);
            }

            if (isset($validated['stock'])) {
                $inv = $vendorProduct->inventory ?? VendorInventory::create(['vendor_product_id' => $vendorProduct->id]);
                $inv->update(['quantity' => (int) $validated['stock']]);
                $product->update(['stock' => $inv->quantity]);
            }

            $this->catalog->persistImages($product, $request);
            $this->syncServicesFromRequest($product, $request);
            $this->catalog->persistOptionGroups($product, $request);

            return $vendorProduct->fresh([
                'product.category',
                'product.services',
                'product.images',
                'product.primaryImage',
                'product.optionGroups.options',
                'product.variants.options',
                'inventory',
                'currentPrice',
            ]);
        });
    }

    public function delete(VendorProduct $vendorProduct): void
    {
        DB::transaction(function () use ($vendorProduct) {
            $vendorProduct->delete();
            $vendorProduct->product?->update(['status' => 'archived']);
        });
    }

    public function findForVendor(Vendor $vendor, int $vendorProductId): ?VendorProduct
    {
        return VendorProduct::with([
            'product.category',
            'product.services',
            'product.images',
            'product.primaryImage',
            'product.optionGroups.options',
            'product.variants.options',
            'inventory',
            'currentPrice',
        ])
            ->where('vendor_id', $vendor->id)
            ->where('id', $vendorProductId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatApiResponse(VendorProduct $vendorProduct): array
    {
        $product = $vendorProduct->product;
        $productData = $product ? $this->catalog->productToApiData($product) : null;

        return [
            'id' => $vendorProduct->id,
            'vendor_id' => $vendorProduct->vendor_id,
            'product_id' => $vendorProduct->product_id,
            'status' => $vendorProduct->status,
            'approval_status' => $vendorProduct->approval_status,
            'rejection_reason' => $vendorProduct->rejection_reason,
            'approved_at' => $vendorProduct->approved_at?->toIso8601String(),
            'inventory' => $vendorProduct->inventory,
            'current_price' => $vendorProduct->currentPrice,
            'product' => $productData,
        ];
    }

    private function syncServicesFromRequest(Product $product, Request $request): void
    {
        if (! $request->has('service_ids') && ! $request->filled('service_id')) {
            return;
        }

        $serviceIds = $this->catalog->resolveServiceIds($request);
        if ($serviceIds === []) {
            $product->services()->sync([]);

            return;
        }

        $product->services()->sync($this->catalog->filterPlatformServiceIds($serviceIds));
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rethrowUniqueProductErrors(\Illuminate\Database\QueryException $e, array $payload): void
    {
        $msg = strtolower($e->getMessage());
        if (! str_contains($msg, 'unique')) {
            throw $e;
        }

        $errors = [];
        if (str_contains($msg, 'handle') || (! empty($payload['handle']) && Product::where('handle', $payload['handle'])->exists())) {
            $errors['handle'] = ['The handle has already been taken.'];
        }
        if (str_contains($msg, 'sku') || (! empty($payload['sku']) && Product::where('sku', $payload['sku'])->exists())) {
            $errors['sku'] = ['The SKU has already been taken.'];
        }
        if ($errors === []) {
            $errors['handle'] = ['A product with this handle or SKU already exists.'];
        }

        throw ValidationException::withMessages($errors);
    }
}
