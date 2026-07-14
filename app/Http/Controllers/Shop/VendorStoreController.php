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
     * Public vendor store page — vendor details + full product catalog.
     */
    public function show(Request $request, int $id): JsonResponse
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
            'sort_by' => 'sometimes|in:sort_order,price,name',
            'sort_dir' => 'sometimes|in:asc,desc',
        ]);

        $products = $this->vendorStore->listProducts($vendor, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Vendor store retrieved successfully',
            'data' => array_merge(
                $this->vendorStore->vendorPayload($vendor, $products->count()),
                [
                    'products' => $products
                        ->map(fn ($product) => $this->vendorStore->productCard($product))
                        ->values()
                        ->all(),
                ]
            ),
        ]);
    }
}
