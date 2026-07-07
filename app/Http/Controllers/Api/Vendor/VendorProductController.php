<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorProduct;
use App\Services\Vendor\VendorProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'items' => collect($items->items())->map(fn (VendorProduct $vp) => $this->products->formatApiResponse($vp))->all(),
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

        try {
            $vp = $this->products->createFromRequest($vendor, $request);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), 422, $e->errors());
        }

        return ApiResponse::success('Product created.', [
            'vendor_product' => $this->products->formatApiResponse($vp),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vp = $this->products->findForVendor($vendor, $id);
        if ($vp === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::success('Product retrieved.', [
            'vendor_product' => $this->products->formatApiResponse($vp),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vp = $this->products->findForVendor($vendor, $id);
        if ($vp === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        try {
            $vp = $this->products->updateFromRequest($vp, $request, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), 422, $e->errors());
        }

        return ApiResponse::success('Product updated.', [
            'vendor_product' => $this->products->formatApiResponse($vp),
        ]);
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
