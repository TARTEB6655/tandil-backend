<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Split shop fulfillment by catalog line type (not by who listed it).
 *
 * - Product lines → vendor owns prep/delivery + customer OTP
 * - Service lines → supervisor → technician → customer confirm
 */
final class OrderFulfillmentType
{
    public const PRODUCT = 'product';

    public const SERVICE = 'service';

    public static function forProduct(?Product $product): string
    {
        if ($product === null) {
            return self::PRODUCT;
        }

        $type = strtolower(trim((string) ($product->type ?? '')));
        if ($type === 'service') {
            return self::SERVICE;
        }

        // Legacy: platform catalog rows linked to Services are treated as service jobs.
        if ($product->relationLoaded('services') && $product->services->isNotEmpty()) {
            return self::SERVICE;
        }

        return self::PRODUCT;
    }

    public static function isServiceProduct(?Product $product): bool
    {
        return self::forProduct($product) === self::SERVICE;
    }

    public static function isProductLine(?Product $product): bool
    {
        return self::forProduct($product) === self::PRODUCT;
    }

    public static function forOrderItem(OrderItem $item): string
    {
        $item->loadMissing('product.services');

        return self::forProduct($item->product);
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public static function serviceItems(Order $order): Collection
    {
        $order->loadMissing('items.product.services');

        return $order->items->filter(
            fn (OrderItem $item) => self::forOrderItem($item) === self::SERVICE
        )->values();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public static function productItems(Order $order): Collection
    {
        $order->loadMissing('items.product.services');

        return $order->items->filter(
            fn (OrderItem $item) => self::forOrderItem($item) === self::PRODUCT
        )->values();
    }

    public static function hasServiceLines(Order $order): bool
    {
        return self::serviceItems($order)->isNotEmpty();
    }

    public static function hasProductLines(Order $order): bool
    {
        return self::productItems($order)->isNotEmpty();
    }

    /**
     * Product-only shop orders use the vendor + OTP timeline.
     * Mixed or service-only orders keep the supervisor/technician timeline.
     */
    public static function usesVendorProductWorkflow(Order $order): bool
    {
        return self::hasProductLines($order) && ! self::hasServiceLines($order);
    }
}
