<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Split shop fulfillment by catalog line type and ownership.
 *
 * - Platform catalog simple (admin /api/admin/products, vendor_id null) → checkout only
 * - Vendor simple (vendor_id set) → vendor prep/delivery + customer OTP
 * - Service lines → supervisor → technician → customer confirm
 */
final class OrderFulfillmentType
{
    /** Vendor-owned simple product — OTP + vendor timeline. */
    public const PRODUCT = 'product';

    /** Admin platform catalog simple — checkout only (no OTP / vendor / supervisor). */
    public const PLATFORM = 'platform';

    public const SERVICE = 'service';

    public static function forProduct(?Product $product): string
    {
        if ($product === null) {
            return self::PLATFORM;
        }

        if (self::isServiceCatalogProduct($product)) {
            return self::SERVICE;
        }

        return (int) ($product->vendor_id ?? 0) > 0 ? self::PRODUCT : self::PLATFORM;
    }

    public static function isServiceProduct(?Product $product): bool
    {
        return self::forProduct($product) === self::SERVICE;
    }

    public static function isProductLine(?Product $product): bool
    {
        $kind = self::forProduct($product);

        return $kind === self::PRODUCT || $kind === self::PLATFORM;
    }

    public static function isVendorFulfillmentProduct(?Product $product): bool
    {
        if ($product === null || self::isServiceCatalogProduct($product)) {
            return false;
        }

        return (int) ($product->vendor_id ?? 0) > 0;
    }

    public static function isPlatformCheckoutProduct(?Product $product): bool
    {
        if ($product === null || self::isServiceCatalogProduct($product)) {
            return false;
        }

        return (int) ($product->vendor_id ?? 0) <= 0;
    }

    /**
     * Service SKU detection without vendor/platform branching (avoids recursion).
     */
    private static function isServiceCatalogProduct(Product $product): bool
    {
        $type = strtolower(trim((string) ($product->type ?? '')));
        if ($type === 'service') {
            return true;
        }

        if (in_array($type, ['product', 'physical', 'digital', 'simple', 'variable'], true)) {
            return false;
        }

        if (! $product->relationLoaded('services')) {
            $product->loadMissing('services');
        }

        return $product->services->isNotEmpty();
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
    public static function vendorProductItems(Order $order): Collection
    {
        $order->loadMissing('items.product.services');

        return $order->items->filter(
            fn (OrderItem $item) => self::forOrderItem($item) === self::PRODUCT
        )->values();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public static function platformProductItems(Order $order): Collection
    {
        $order->loadMissing('items.product.services');

        return $order->items->filter(
            fn (OrderItem $item) => self::forOrderItem($item) === self::PLATFORM
        )->values();
    }

    /** @deprecated use vendorProductItems */
    public static function productItems(Order $order): Collection
    {
        return self::vendorProductItems($order);
    }

    public static function hasServiceLines(Order $order): bool
    {
        return self::serviceItems($order)->isNotEmpty();
    }

    public static function hasVendorProductLines(Order $order): bool
    {
        return self::vendorProductItems($order)->isNotEmpty();
    }

    public static function hasPlatformProductLines(Order $order): bool
    {
        return self::platformProductItems($order)->isNotEmpty();
    }

    /** @deprecated use hasVendorProductLines */
    public static function hasProductLines(Order $order): bool
    {
        return self::hasVendorProductLines($order) || self::hasPlatformProductLines($order);
    }

    /** Vendor OTP timeline — vendor-owned simple lines only, no service lines. */
    public static function usesVendorProductWorkflow(Order $order): bool
    {
        return self::hasVendorProductLines($order) && ! self::hasServiceLines($order);
    }

    /** Admin platform catalog checkout — no vendor_id, no OTP, no supervisor. */
    public static function usesPlatformCheckoutWorkflow(Order $order): bool
    {
        return self::hasPlatformProductLines($order)
            && ! self::hasServiceLines($order)
            && ! self::hasVendorProductLines($order);
    }
}
