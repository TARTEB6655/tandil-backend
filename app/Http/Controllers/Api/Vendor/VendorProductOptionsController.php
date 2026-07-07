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
        $items = Service::query()
            ->vendorAssignable()
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
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
