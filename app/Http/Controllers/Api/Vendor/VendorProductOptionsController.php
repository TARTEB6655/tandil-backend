<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProductOptionsController extends Controller
{
    /**
     * Platform categories for the Add Product dropdown (id + name only).
     * For listing this vendor's products by category use GET /api/vendor/products?category_id=
     */
    public function categories(Request $request): JsonResponse
    {
        $items = Category::query()
            ->vendorAssignable()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();

        return ApiResponse::success('Categories retrieved.', ['items' => $items]);
    }

    /**
     * Platform services for the Add Product dropdown (id + name only).
     */
    public function services(Request $request): JsonResponse
    {
        $categoryId = $request->filled('category_id') ? (int) $request->query('category_id') : null;

        $items = Service::query()
            ->vendorAssignable()
            ->forProductCategory($categoryId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
            ])
            ->values()
            ->all();

        return ApiResponse::success('Services retrieved.', ['items' => $items]);
    }
}
