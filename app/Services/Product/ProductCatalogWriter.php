<?php

namespace App\Services\Product;

use App\Http\Controllers\Admin\ProductController;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductCatalogWriter
{
    public const PRODUCT_API_FIELDS = [
        'name', 'description', 'price', 'stock', 'status', 'is_featured', 'sort_order', 'category_id', 'weight_unit', 'sku', 'handle', 'product_type',
        'estimated_arrival', 'job_duration',
    ];

    public function __construct(
        private readonly ProductController $adminProducts
    ) {}

    public function prepareRequest(Request $request): void
    {
        $categoryIdRaw = $request->input('category_id') ?? $request->request->get('category_id');
        if ($categoryIdRaw !== null && $categoryIdRaw !== '') {
            $request->merge(['category_id' => is_array($categoryIdRaw) ? ($categoryIdRaw[0] ?? null) : $categoryIdRaw]);
        } elseif ($request->has('category_id') && $request->category_id === '') {
            $request->merge(['category_id' => null]);
        }

        $imageUrls = null;
        if ($request->has('image_urls')) {
            $v = $request->input('image_urls');
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                $imageUrls = is_array($decoded) ? array_values($decoded) : null;
            } elseif (is_array($v)) {
                $imageUrls = array_values($v);
            }
        }
        if ($imageUrls === null && $request->has('image_url')) {
            $imageUrls = array_values((array) $request->input('image_url'));
        }
        if ($imageUrls !== null) {
            $request->merge(['image_urls' => $imageUrls]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function storeRules(?int $existingProductId = null): array
    {
        $skuRule = $existingProductId
            ? 'nullable|string|max:255|unique:products,sku,'.$existingProductId
            : 'nullable|string|max:255|unique:products,sku';
        $handleRule = $existingProductId
            ? 'nullable|string|max:255|unique:products,handle,'.$existingProductId
            : 'nullable|string|max:255|unique:products,handle';

        return [
            'name' => $existingProductId ? 'nullable|string|max:255' : 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => $existingProductId ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:draft,active,archived',
            'vendor_product_status' => 'nullable|in:active,inactive',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'category_id' => $existingProductId ? 'nullable|integer' : 'required|integer',
            'weight_unit' => 'nullable|in:kg,g,lb,oz',
            'sku' => $skuRule,
            'handle' => $handleRule,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'main_image' => ['nullable', $this->mainImageValidationRule()],
            'main_image.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'nullable|string|url',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'estimated_arrival' => 'nullable|string|max:255',
            'job_duration' => 'nullable|string|max:255',
            'product_type' => 'nullable|in:simple,variable',
            'option_groups_json' => 'nullable',
            'option_groups' => 'nullable',
            'option_images' => 'nullable|array',
            'option_images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildCreateData(Request $request, array $validated, array $overrides = []): array
    {
        $createData = [];
        foreach (self::PRODUCT_API_FIELDS as $key) {
            $value = $request->input($key) ?? $request->request->get($key) ?? ($validated[$key] ?? null);
            if ($value instanceof UploadedFile || is_array($value)) {
                continue;
            }
            if ($value !== null && $value !== '') {
                $createData[$key] = $value;
            }
        }

        $createData['name'] = $createData['name'] ?? $validated['name'] ?? '';
        $createData['price'] = $createData['price'] ?? $validated['price'] ?? 0;
        $createData['status'] = $createData['status'] ?? $validated['status'] ?? 'draft';
        $createData['is_featured'] = $request->has('is_featured') ? $request->boolean('is_featured') : false;
        $createData['weight_unit'] = $createData['weight_unit'] ?? $validated['weight_unit'] ?? 'kg';
        $createData['stock'] = $createData['stock'] ?? $validated['stock'] ?? 0;
        $createData['product_type'] = $request->input('product_type', 'simple');

        if (empty($createData['handle']) && ! empty($createData['name'])) {
            $createData['handle'] = Str::slug($createData['name']);
            $counter = 1;
            $original = $createData['handle'];
            while (Product::where('handle', $createData['handle'])->exists()) {
                $createData['handle'] = $original.'-'.$counter++;
            }
        }

        $rawCategoryId = $request->input('category_id') ?? $request->request->get('category_id') ?? ($validated['category_id'] ?? null);
        if (is_array($rawCategoryId)) {
            $rawCategoryId = $rawCategoryId[0] ?? null;
        }
        if ($rawCategoryId !== null && $rawCategoryId !== '' && is_numeric($rawCategoryId)) {
            $cid = (int) $rawCategoryId;
            $createData['category_id'] = Category::find($cid) ? $cid : null;
        } else {
            $createData['category_id'] = null;
        }

        if ($createData['category_id'] === null && Schema::getConnection()->getDriverName() === 'sqlite') {
            $firstCategory = Category::orderBy('id')->first();
            if ($firstCategory) {
                $createData['category_id'] = $firstCategory->id;
            }
        }

        if (! isset($createData['sort_order']) || $createData['sort_order'] === '') {
            $createData['sort_order'] = Product::nextSortOrder($createData['category_id'] ?? null);
        } else {
            $createData['sort_order'] = (int) $createData['sort_order'];
        }

        return array_merge($createData, $overrides);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function buildUpdateData(Request $request, array $validated): array
    {
        $updateData = [];
        foreach (self::PRODUCT_API_FIELDS as $key) {
            $value = $request->input($key) ?? $request->request->get($key);
            if ($value === null && ! $request->has($key) && ! $request->request->has($key)) {
                continue;
            }
            if ($value instanceof UploadedFile || is_array($value)) {
                continue;
            }
            $updateData[$key] = $value;
        }

        foreach (['estimated_arrival', 'job_duration'] as $timingKey) {
            if (array_key_exists($timingKey, $updateData) && $updateData[$timingKey] === '') {
                $updateData[$timingKey] = null;
            }
        }

        if ($request->has('is_featured')) {
            $updateData['is_featured'] = $request->boolean('is_featured');
        }

        if (array_key_exists('category_id', $updateData)) {
            $rawCategoryId = $updateData['category_id'];
            if (is_array($rawCategoryId)) {
                $rawCategoryId = $rawCategoryId[0] ?? null;
            }
            if ($rawCategoryId !== null && $rawCategoryId !== '' && is_numeric($rawCategoryId)) {
                $cid = (int) $rawCategoryId;
                $updateData['category_id'] = Category::find($cid) ? $cid : null;
            } else {
                $updateData['category_id'] = null;
            }
        }

        return array_filter($updateData, fn ($v) => $v !== null);
    }

    public function persistImages(Product $product, Request $request): void
    {
        [$mainFile, $extraFromMain] = $this->normalizeMainImageInput($request);
        $extraFiles = $extraFromMain;
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            $extraFiles = array_merge($extraFiles, is_array($files) ? $files : [$files]);
        }
        if ($request->hasFile('images[]')) {
            $files = $request->file('images[]');
            $extraFiles = array_merge($extraFiles, is_array($files) ? $files : [$files]);
        }
        $extraFiles = array_values(array_filter($extraFiles, fn ($f) => $f && $f->isValid()));

        $sortOrder = (int) ProductImage::where('product_id', $product->id)->max('sort_order') + 1;
        if ($sortOrder < 0) {
            $sortOrder = 0;
        }

        if ($mainFile !== null) {
            $imagePath = $mainFile->store('products', 'public');
            $this->scheduleProductImageOptimization($imagePath);
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => $sortOrder++,
                'is_primary' => ! ProductImage::where('product_id', $product->id)->where('is_primary', true)->exists(),
            ]);
            $product->update(['image' => $imagePath]);
        }

        foreach ($extraFiles as $image) {
            $imagePath = $image->store('products', 'public');
            $this->scheduleProductImageOptimization($imagePath);
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => $sortOrder++,
                'is_primary' => false,
            ]);
        }

        if ($mainFile === null && $extraFiles !== [] && ! $product->image) {
            $first = ProductImage::where('product_id', $product->id)->orderBy('sort_order')->first();
            if ($first) {
                $first->update(['is_primary' => true]);
                $product->update(['image' => $first->image_path]);
            }
        }

        $primaryAlreadySet = ($mainFile !== null || $extraFiles !== []);
        if ($request->has('image_urls') && is_array($request->image_urls)) {
            foreach ($request->image_urls as $imageUrl) {
                if (is_string($imageUrl) && $imageUrl !== '') {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imageUrl,
                        'sort_order' => $sortOrder,
                        'is_primary' => ! $primaryAlreadySet && $sortOrder === 0,
                    ]);
                    $sortOrder++;
                }
            }
        }

        $firstImage = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first()
            ?? ProductImage::where('product_id', $product->id)->orderBy('sort_order')->first();
        if ($firstImage && ! $product->image) {
            $product->update(['image' => $firstImage->image_path]);
        }

        $this->reorderProductImages($product->id);
    }

    /**
     * @param  callable(int): bool|null  $categoryValidator
     */
    public function assertCategoryAllowed(?int $categoryId, callable $categoryValidator): void
    {
        if ($categoryId === null) {
            throw new \InvalidArgumentException('A platform category is required.');
        }
        if (! $categoryValidator($categoryId)) {
            throw new \InvalidArgumentException('Invalid platform category.');
        }
    }

    /**
     * @return list<int>
     */
    public function resolveServiceIds(Request $request): array
    {
        if ($request->filled('service_id')) {
            return [(int) $request->service_id];
        }
        if ($request->has('service_ids') && is_array($request->service_ids)) {
            return array_values(array_filter(array_map('intval', $request->service_ids)));
        }

        return [];
    }

    /**
     * @param  list<int>  $serviceIds
     * @return list<int>
     */
    public function filterPlatformServiceIds(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $allowed = Service::platformCatalog()->whereIn('id', $serviceIds)->pluck('id')->all();
        if (count($allowed) !== count($serviceIds)) {
            throw new \InvalidArgumentException('One or more services are not available on the platform.');
        }

        return $allowed;
    }

    public function persistOptionGroups(Product $product, Request $request): void
    {
        $this->adminProducts->syncOptionGroupsForProduct($product, $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function productToApiData(Product $product): array
    {
        return $this->adminProducts->serializeProductForApi($product);
    }

  /**
     * @return array{0: ?UploadedFile, 1: array<int, UploadedFile>}
     */
    private function normalizeMainImageInput(Request $request): array
    {
        $mainFile = null;
        $extraFromMain = [];
        foreach (['main_image', 'image'] as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $f = $request->file($field);
            if (is_array($f)) {
                $valid = array_values(array_filter($f, fn ($file) => $file && $file->isValid()));
                if (count($valid) > 0) {
                    $mainFile = $valid[0];
                    $extraFromMain = array_slice($valid, 1);
                }
            } elseif ($f && $f->isValid()) {
                $mainFile = $f;
            }
            if ($mainFile !== null) {
                break;
            }
        }

        return [$mainFile, $extraFromMain];
    }

    private function reorderProductImages(int $productId): void
    {
        $images = ProductImage::where('product_id', $productId)->orderByRaw('is_primary DESC')->orderBy('sort_order')->get();
        foreach ($images as $index => $img) {
            if ((int) $img->sort_order !== $index) {
                $img->update(['sort_order' => $index]);
            }
        }
    }

    private function scheduleProductImageOptimization(string $relativePath): void
    {
        $profile = 'gallery';
        if (app()->runningUnitTests()) {
            (new \App\Jobs\OptimizePublicDiskImageJob($relativePath, $profile))->handle();

            return;
        }

        \App\Jobs\OptimizePublicDiskImageJob::dispatch($relativePath, $profile)->afterResponse();
    }

    private function mainImageValidationRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if ($value === null) {
                return;
            }
            $files = is_array($value) ? $value : [$value];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            foreach ($files as $f) {
                if (! $f instanceof UploadedFile) {
                    $fail(__('The main image must be a valid image file.'));

                    return;
                }
                if (! $f->isValid()) {
                    $fail(__('The main image file is invalid.'));

                    return;
                }
                $mime = $f->getMimeType();
                if (! in_array($mime, $allowed, true)) {
                    $fail(__('The main image must be a file of type: jpeg, jpg, png, webp.'));

                    return;
                }
            }
        };
    }
}
