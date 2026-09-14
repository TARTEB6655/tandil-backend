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
            'vendor_product_id' => $vp->id,
            'product_id' => $vp->product_id,
            'stock_quantity' => $vp->stockQuantity(),
            'stock' => $vp->stockQuantity(),
            'quantity' => $vp->stockQuantity(),
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
            return ApiResponse::error('Not found. Use vendor_product_id from product list (or catalog product_id).', 404);
        }

        // Mobile often sends stock / stock_quantity / camelCase instead of quantity.
        $this->normalizeInventoryInput($request);

        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ], [
            'quantity.required' => 'Send quantity (or stock / stock_quantity) as a non-negative integer.',
        ]);

        $inventory = $this->inventory->adjust(
            $vp,
            (int) $data['quantity'],
            $request->user(),
            'manual_update',
            $data['notes'] ?? null
        );

        if (array_key_exists('low_stock_threshold', $data) && $data['low_stock_threshold'] !== null) {
            $inventory->update(['low_stock_threshold' => (int) $data['low_stock_threshold']]);
        }

        $vp = $vp->fresh(['inventory', 'product']);
        $qty = $vp->stockQuantity();

        return ApiResponse::success('Inventory updated.', [
            'vendor_product_id' => $vp->id,
            'product_id' => $vp->product_id,
            'stock_quantity' => $qty,
            'stock' => $qty,
            'quantity' => $qty,
            'low_stock_threshold' => $vp->lowStockThreshold(),
            'is_low_stock' => $vp->isLowStock(),
            'is_out_of_stock' => $vp->isOutOfStock(),
            'inventory' => $inventory->fresh(),
        ]);
    }

    private function normalizeInventoryInput(Request $request): void
    {
        $qty = $request->input('quantity', $request->input('stock', $request->input('stock_quantity', $request->input('stockQuantity'))));
        if ($qty !== null && $qty !== '' && ! $request->filled('quantity')) {
            $request->merge(['quantity' => $qty]);
        }

        $threshold = $request->input(
            'low_stock_threshold',
            $request->input('lowStockThreshold', $request->input('low_stock'))
        );
        if ($threshold !== null && $threshold !== '' && ! $request->exists('low_stock_threshold')) {
            $request->merge(['low_stock_threshold' => $threshold]);
        }

        $notes = $request->input('notes', $request->input('note', $request->input('remark')));
        if (is_string($notes) && $notes !== '' && ! $request->filled('notes')) {
            $request->merge(['notes' => $notes]);
        }
    }
}
