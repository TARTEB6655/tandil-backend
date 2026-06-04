<?php

namespace App\Services\Vendor;

use App\Models\User;
use App\Models\VendorInventory;
use App\Models\VendorInventoryLog;
use App\Models\VendorProduct;
use Illuminate\Support\Facades\DB;

class VendorInventoryService
{
    public function adjust(VendorProduct $vendorProduct, int $newQuantity, User $user, string $changeType = 'adjustment', ?string $notes = null): VendorInventory
    {
        return DB::transaction(function () use ($vendorProduct, $newQuantity, $user, $changeType, $notes) {
            $inventory = $vendorProduct->inventory ?? VendorInventory::create([
                'vendor_product_id' => $vendorProduct->id,
                'quantity' => 0,
                'low_stock_threshold' => 5,
            ]);

            $before = $inventory->quantity;
            $inventory->update(['quantity' => max(0, $newQuantity)]);
            $vendorProduct->product?->update(['stock' => $inventory->quantity]);

            VendorInventoryLog::create([
                'vendor_product_id' => $vendorProduct->id,
                'change_type' => $changeType,
                'quantity_before' => $before,
                'quantity_after' => $inventory->quantity,
                'changed_by' => $user->id,
                'notes' => $notes,
            ]);

            return $inventory->fresh();
        });
    }
}
