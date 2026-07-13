<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorComparisonController extends Controller
{
    public function __construct(
        private readonly VendorComparisonService $comparison
    ) {}

    public function byProduct(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'sort_by' => 'sometimes|in:price,rating,delivery',
        ]);

        return ApiResponse::success(
            'Vendor comparison.',
            $this->comparison->compareByProduct($productId, $validated['sort_by'] ?? 'price')
        );
    }

    public function byProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'sort_by' => 'sometimes|in:price,rating,delivery',
        ]);

        return ApiResponse::success(
            'Vendor comparison.',
            $this->comparison->compareProducts($data['product_ids'], $data['sort_by'] ?? 'price')
        );
    }
}
