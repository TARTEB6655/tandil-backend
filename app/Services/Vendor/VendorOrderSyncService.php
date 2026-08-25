<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Support\MarketplaceSettings;
use App\Support\OrderFulfillmentType;
use Illuminate\Support\Facades\DB;

class VendorOrderSyncService
{
    /**
     * Create or refresh vendor order mappings from shop order line items.
     */
    public function syncFromOrder(Order $order): void
    {
        if (! $order->isShopOrder()) {
            return;
        }

        $order->loadMissing('items.product.services');

        $byVendor = [];
        foreach ($order->items as $item) {
            if (! OrderFulfillmentType::isVendorFulfillmentProduct($item->product)) {
                continue;
            }
            $vendorId = (int) $item->product->vendor_id;
            if ($vendorId <= 0) {
                continue;
            }
            if (! isset($byVendor[$vendorId])) {
                $byVendor[$vendorId] = 0.0;
            }
            $byVendor[$vendorId] += (float) $item->subtotal;
        }

        if ($byVendor === []) {
            return;
        }

        DB::transaction(function () use ($order, $byVendor) {
            $orderSubtotal = (float) ($order->subtotal_amount ?? $order->items->sum('subtotal'));
            $orderTax = (float) ($order->tax_amount ?? 0);
            $orderShipping = (float) ($order->shipping_amount ?? 0);

            foreach ($byVendor as $vendorId => $subtotal) {
                $share = $orderSubtotal > 0 ? $subtotal / $orderSubtotal : 0;
                $tax = round($orderTax * $share, 2);
                $shipping = round($orderShipping * $share, 2);
                $total = round($subtotal + $tax + $shipping, 2);

                $vendor = Vendor::find($vendorId);
                $rate = MarketplaceSettings::effectiveCommissionForVendor($vendor);
                $commission = round($total * ($rate / 100), 2);

                $mapping = VendorOrderMapping::updateOrCreate(
                    ['order_id' => $order->id, 'vendor_id' => $vendorId],
                    [
                        'subtotal' => round($subtotal, 2),
                        'tax_amount' => $tax,
                        'shipping_amount' => $shipping,
                        'total_amount' => $total,
                        'commission_amount' => $commission,
                    ]
                );

                if ($mapping->wasRecentlyCreated) {
                    $mapping->update(['status' => VendorOrderStatus::Pending->value]);
                    VendorOrderStatusLog::create([
                        'vendor_order_mapping_id' => $mapping->id,
                        'status' => VendorOrderStatus::Pending->value,
                        'changed_by' => null,
                        'note' => 'Order placed.',
                    ]);
                }
            }
        });
    }
}
