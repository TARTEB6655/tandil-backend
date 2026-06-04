<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorProduct;
use App\Services\Vendor\VendorProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProductController extends Controller
{
    public function __construct(
        private readonly VendorProductService $products
    ) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $items = VendorProduct::with(['product.category', 'inventory', 'currentPrice'])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ApiResponse::success('Products retrieved.', [
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,draft,archived',
            'sku' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:5120',
        ]);

        $vp = $this->products->create($vendor, $data, $request->file('image'));

        return ApiResponse::success('Product created.', ['vendor_product' => $vp], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vp = $this->products->findForVendor($vendor, $id);
        if ($vp === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::success('Product retrieved.', ['vendor_product' => $vp]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vp = $this->products->findForVendor($vendor, $id);
        if ($vp === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,draft,archived',
            'vendor_product_status' => 'nullable|in:active,inactive',
            'sku' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:5120',
        ]);

        $vp = $this->products->update($vp, $data, $request->file('image'), false, $request->user()->id);

        return ApiResponse::success('Product updated.', ['vendor_product' => $vp]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vp = $this->products->findForVendor($vendor, $id);
        if ($vp === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        $this->products->delete($vp);

        return ApiResponse::success('Product deleted.');
    }
}
