<?php

namespace App\Services\Shop;

use App\Models\Order;
use App\Models\Product;
use App\Models\VendorProduct;
use App\Services\Vendor\VendorInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Commit / restore on-hand stock when a shop order is paid or cancelled.
 * Keeps products.stock and vendor_inventory.quantity in sync for vendor listings.
 */
final class OrderStockService
{
    public function __construct(
        private readonly VendorInventoryService $vendorInventory
    ) {}

    public function decrementForPaidOrder(Order $order): void
    {
        if (strtolower((string) ($order->payment_status ?? '')) !== 'paid') {
            return;
        }

        try {
            DB::transaction(function () use ($order) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if ($locked === null || $locked->stock_decremented_at !== null) {
                    return;
                }

                $locked->loadMissing(['items.product.vendorProduct.inventory', 'user']);

                foreach ($locked->items as $item) {
                    $qty = max(0, (int) $item->quantity);
                    if ($qty === 0) {
                        continue;
                    }

                    $product = $item->product;
                    if ($product === null) {
                        continue;
                    }

                    $this->applyDelta($product, -$qty, $locked, 'sale');
                }

                $locked->stock_decremented_at = now();
                $locked->save();
            });
        } catch (\Throwable $e) {
            Log::warning('Order stock decrement failed: '.$e->getMessage(), [
                'order_id' => $order->id ?? null,
            ]);
        }
    }

    public function restoreForCancelledOrder(Order $order): void
    {
        try {
            DB::transaction(function () use ($order) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                if ($locked === null || $locked->stock_decremented_at === null) {
                    return;
                }

                $locked->loadMissing(['items.product.vendorProduct.inventory', 'user']);

                foreach ($locked->items as $item) {
                    $qty = max(0, (int) $item->quantity);
                    if ($qty === 0) {
                        continue;
                    }

                    $product = $item->product;
                    if ($product === null) {
                        continue;
                    }

                    $this->applyDelta($product, $qty, $locked, 'sale_restore');
                }

                $locked->stock_decremented_at = null;
                $locked->save();
            });
        } catch (\Throwable $e) {
            Log::warning('Order stock restore failed: '.$e->getMessage(), [
                'order_id' => $order->id ?? null,
            ]);
        }
    }

    private function applyDelta(Product $product, int $delta, Order $order, string $changeType): void
    {
        $product->loadMissing('vendorProduct.inventory');
        $vendorProduct = $product->vendorProduct;

        if ($vendorProduct instanceof VendorProduct) {
            $current = $vendorProduct->stockQuantity();
            $newQty = max(0, $current + $delta);
            $this->vendorInventory->adjust(
                $vendorProduct,
                $newQty,
                $order->user,
                $changeType,
                "order_sale:{$order->id}"
            );

            return;
        }

        $product->refresh();
        $product->stock = max(0, (int) $product->stock + $delta);
        $product->save();
    }
}
