<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorInventoryLog;
use App\Services\Vendor\VendorInventoryService;
use App\Services\Vendor\VendorProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorInventoryController extends Controller
{
    public function __construct(
        private readonly VendorProductService $products,
        private readonly VendorInventoryService $inventory
    ) {}

    public function show(Request $request, int $vendorProductId): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vp = $this->products->findForVendor($vendor, $vendorProductId);
        if ($vp === null) {
            return ApiResponse::error('Not found.', 404);
        }

        return ApiResponse::success('Inventory retrieved.', [
            'stock_quantity' => $vp->stockQuantity(),
            'low_stock_threshold' => $vp->lowStockThreshold(),
            'is_low_stock' => $vp->isLowStock(),
            'is_out_of_stock' => $vp->isOutOfStock(),
            'inventory' => $vp->inventory,
            'history' => VendorInventoryLog::where('vendor_product_id', $vp->id)->latest()->limit(50)->get(),
        ]);
    }

    public function update(Request $request, int $vendorProductId): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $vp = $this->products->findForVendor($vendor, $vendorProductId);
        if ($vp === null) {
            return ApiResponse::error('Not found.', 404);
        }

        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $inventory = $this->inventory->adjust($vp, (int) $data['quantity'], $request->user(), 'manual_update', $data['notes'] ?? null);

        if (isset($data['low_stock_threshold'])) {
            $inventory->update(['low_stock_threshold' => (int) $data['low_stock_threshold']]);
        }

        $vp = $vp->fresh(['inventory', 'product']);

        return ApiResponse::success('Inventory updated.', [
            'stock_quantity' => $vp->stockQuantity(),
            'inventory' => $inventory->fresh(),
        ]);
    }
}
