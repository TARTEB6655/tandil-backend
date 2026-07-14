<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\VendorStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorStoreController extends Controller
{
    public function __construct(
        private readonly VendorStoreService $vendorStore
    ) {}

    /**
     * Vendor store header for client app (logo, name, summary).
     */
    public function show(int $id): JsonResponse
    {
        $vendor = $this->vendorStore->findVisibleVendor($id);
        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vendor store retrieved successfully',
            'data' => $this->vendorStore->vendorPayload($vendor),
        ]);
    }

    /**
     * Live product catalog for a vendor store page.
     */
    public function products(Request $request, int $id): JsonResponse
    {
        $vendor = $this->vendorStore->findVisibleVendor($id);
        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        $validated = $request->validate([
            'search' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'sort_by' => 'sometimes|in:sort_order,price,name',
            'sort_dir' => 'sometimes|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $paginator = $this->vendorStore->paginateProducts(
            $vendor,
            $validated,
            (int) ($validated['per_page'] ?? 12)
        );

        return response()->json([
            'success' => true,
            'message' => 'Vendor products retrieved successfully',
            'data' => [
                'vendor' => $this->vendorStore->vendorPayload($vendor, $paginator->total()),
                'products' => collect($paginator->items())
                    ->map(fn ($product) => $this->vendorStore->productCard($product))
                    ->values()
                    ->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }
}
