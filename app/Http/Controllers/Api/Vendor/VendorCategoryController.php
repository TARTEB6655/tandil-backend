<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Vendor;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $categories = Category::query()
            ->forVendorCatalog($vendor->id)
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->ordered()
            ->paginate($perPage);

        return ApiResponse::success('Categories retrieved.', [
            'items' => collect($categories->items())->map(fn (Category $c) => $this->toArray($c))->all(),
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $validated = $request->validated();
        $name = $validated['name'] ?? '';
        $slug = isset($validated['slug']) && (string) $validated['slug'] !== '' ? $validated['slug'] : Str::slug($name);
        $slug = $this->uniqueSlug($slug);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
            ImageCompressionService::compressIfNeededFromPublicPath($imagePath);
        }

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'sort_order' => $request->filled('sort_order') ? max(1, (int) $request->input('sort_order')) : Category::nextSortOrder(),
            'shipping_cost' => $validated['shipping_cost'] ?? 0,
            'tax_percentage' => $validated['tax_percentage'] ?? 0,
            'shipping_type' => $request->input('shipping_type'),
        ]);

        return ApiResponse::success('Category created.', ['category' => $this->toArray($category)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $category = $this->findAccessible($vendor, $id);
        if ($category === null) {
            return ApiResponse::error('Category not found.', 404);
        }

        return ApiResponse::success('Category retrieved.', ['category' => $this->toArray($category)]);
    }

    public function update(CategoryRequest $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $category = Category::where('vendor_id', $vendor->id)->where('id', $id)->first();
        if ($category === null) {
            return ApiResponse::error('You can only update categories created by your store.', 403);
        }

        $validated = $request->validated();
        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'sort_order' => $request->filled('sort_order') ? (int) $request->input('sort_order') : null,
            'shipping_cost' => $validated['shipping_cost'] ?? null,
            'tax_percentage' => $validated['tax_percentage'] ?? null,
            'shipping_type' => $request->input('shipping_type'),
        ], fn ($v) => $v !== null);

        if ($request->hasFile('image')) {
            $updates['image'] = $request->file('image')->store('categories', 'public');
            app(ImageCompressionService::class)->compressIfImage('public', $updates['image']);
        } elseif ($request->boolean('image_remove')) {
            $updates['image'] = null;
        }

        $category->update($updates);

        return ApiResponse::success('Category updated.', ['category' => $this->toArray($category->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $category = Category::where('vendor_id', $vendor->id)->where('id', $id)->first();
        if ($category === null) {
            return ApiResponse::error('You can only delete categories created by your store.', 403);
        }

        $category->delete();

        return ApiResponse::success('Category deleted.');
    }

    private function findAccessible(Vendor $vendor, int $id): ?Category
    {
        return Category::query()
            ->forVendorCatalog($vendor->id)
            ->where('id', $id)
            ->first();
    }

    private function uniqueSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Category $category): array
    {
        return [
            'id' => $category->id,
            'vendor_id' => $category->vendor_id,
            'is_platform' => $category->vendor_id === null,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->image,
            'image_url' => $category->image_url,
            'is_active' => (bool) $category->is_active,
            'sort_order' => (int) ($category->sort_order ?? 0),
            ...$category->shippingTaxConfigForApi(),
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
