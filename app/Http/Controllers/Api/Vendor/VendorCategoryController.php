<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $categories = Category::query()
            ->vendorAssignable()
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

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::error('Vendors cannot create categories. Use platform categories when adding products.', 403);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $category = Category::vendorAssignable()->where('id', $id)->first();
        if ($category === null) {
            return ApiResponse::error('Category not found.', 404);
        }

        return ApiResponse::success('Category retrieved.', ['category' => $this->toArray($category)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return ApiResponse::error('Vendors cannot update categories. Use platform categories when adding products.', 403);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return ApiResponse::error('Vendors cannot delete categories.', 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Category $category): array
    {
        return [
            'id' => $category->id,
            'vendor_id' => $category->vendor_id,
            'is_platform' => true,
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
