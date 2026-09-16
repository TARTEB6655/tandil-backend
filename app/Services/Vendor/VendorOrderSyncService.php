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
            // Include vendor-owned simple AND service listings so both appear on GET /api/vendor/orders.
            // Service fulfillment stays on supervisor path (OTP/ship blocked for service mappings).
            if (! OrderFulfillmentType::isVendorOwnedListing($item->product)) {
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

                $attrs = [
                    'subtotal' => round($subtotal, 2),
                    'tax_amount' => $tax,
                    'shipping_amount' => $shipping,
                    'total_amount' => $total,
                    'commission_amount' => $commission,
                ];
                $productTitle = $this->productTitleForVendor($order, $vendorId);
                if ($productTitle !== null) {
                    $attrs['product_title'] = $productTitle;
                }

                $mapping = VendorOrderMapping::updateOrCreate(
                    ['order_id' => $order->id, 'vendor_id' => $vendorId],
                    $attrs
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

    private function productTitleForVendor(Order $order, int $vendorId): ?string
    {
        $titles = [];
        foreach ($order->items as $item) {
            if (! OrderFulfillmentType::isVendorOwnedListing($item->product)) {
                continue;
            }
            if ((int) ($item->product->vendor_id ?? 0) !== $vendorId) {
                continue;
            }
            $name = trim((string) ($item->product_name ?: $item->product?->name ?: ''));
            if ($name !== '' && strcasecmp($name, 'Product') !== 0) {
                $titles[$name] = true;
            }
        }

        if ($titles === []) {
            return null;
        }

        return implode(', ', array_keys($titles));
    }
}
