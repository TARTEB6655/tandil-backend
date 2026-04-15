<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public const CURRENCY = 'AED';

    /**
     * Effective shipping amount (admin API or config). 0 = free.
     */
    public static function getEffectiveShippingAmount(): float
    {
        $v = Setting::get('shop_shipping_amount');
        return (float) ($v !== null && $v !== '' ? $v : config('shop.shipping_amount', 0));
    }

    /**
     * Effective tax percentage (admin API or config). e.g. 5 = 5%.
     */
    public static function getEffectiveTaxPercent(): float
    {
        $v = Setting::get('shop_tax_percent');
        return (float) ($v !== null && $v !== '' ? $v : config('shop.tax_percent', 5));
    }

    /**
     * Build order summary: 1-Subtotal, 2-Shipping (free or amount), 3-Tax (%), 4-Total.
     * Tax-exclusive pricing: product prices are without tax; tax is added at checkout.
     * Tax = Subtotal × (tax_percent / 100). Total = Subtotal - Discount + Shipping + Tax.
     * Same result as per-product tax when all products have the same tax rate.
     */
    public static function buildOrderSummary(float $subtotal, float $discount = 0): array
    {
        $shippingAmount = self::getEffectiveShippingAmount();
        $taxPercent = self::getEffectiveTaxPercent();
        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal - $discount + $shippingAmount + $taxAmount, 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shippingAmount,
            'shipping_label' => $shippingAmount == 0 ? 'Free' : (string) $shippingAmount,
            'tax_percent' => $taxPercent,
            'tax' => $taxAmount,
            'total' => $total,
            'currency' => self::CURRENCY,
        ];
    }

    /**
     * Format a cart item for frontend (Product Details / Shopping Cart / Review screens).
     */
    public static function cartItemToFrontend(Cart $item): array
    {
        $product = $item->product;
        $price = (float) $product->price;
        $compareAt = $product->compare_at_price !== null ? (float) $product->compare_at_price : null;
        $lineTotal = round($item->quantity * $price, 2);

        return [
            'id' => $item->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'image_url' => $product->image_url,
            'category' => $product->relationLoaded('category') && $product->category
                ? $product->category->name
                : null,
            'brand' => $product->vendor ?? null,
            'current_price' => $price,
            'original_price' => $compareAt,
            'quantity' => $item->quantity,
            'line_total' => $lineTotal,
            'currency' => self::CURRENCY,
        ];
    }

    /**
     * Add item to cart (Product Details → Add to Cart).
     */
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $product = Product::findOrFail($request->product_id);

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $request->quantity;
            $cartItem->save();
        } else {
            $cartItem = Cart::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        $cartItem->load(['product.category', 'product.primaryImage']);
        $data = self::cartItemToFrontend($cartItem);

        return ApiResponse::success('Item added to cart.', $data, 201);
    }

    /**
     * Cart lines + subtotal for checkout preview. Default = DB cart.
     * Optional query product_id + quantity = Buy Now without persisting cart (same idea as POST create-payment-session with items).
     *
     * @return array{items: \Illuminate\Support\Collection<int, Cart>, subtotal: float}
     */
    public static function checkoutPreview(Request $request, int $userId): array
    {
        if ($request->filled('product_id')) {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'sometimes|integer|min:1',
                'qty' => 'sometimes|integer|min:1',
            ]);
            // quantity preferred; else qty (same as POST create-payment-session items.*.qty)
            if ($request->filled('quantity')) {
                $qty = max(1, (int) $request->query('quantity'));
            } elseif ($request->filled('qty')) {
                $qty = max(1, (int) $request->query('qty'));
            } else {
                $qty = 1;
            }
            $product = Product::with(['category', 'primaryImage'])->findOrFail((int) $request->query('product_id'));
            $cart = new Cart([
                'user_id' => $userId,
                'product_id' => $product->id,
                'quantity' => $qty,
            ]);
            $cart->setRelation('product', $product);
            $cart->id = 0;
            $subtotal = round($qty * (float) $product->price, 2);

            return [
                'items' => collect([$cart]),
                'subtotal' => $subtotal,
            ];
        }

        $cartItems = Cart::where('user_id', $userId)
            ->with(['product.category', 'product.primaryImage'])
            ->get();
        $validItems = $cartItems->filter(fn ($item) => $item->product !== null)->values();
        $subtotal = round($validItems->sum(fn ($item) => $item->quantity * (float) $item->product->price), 2);

        return [
            'items' => $validItems,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * GET /api/shop/order-summary
     * Returns order summary for checkout (Address/Payment/Review).
     * Tax-exclusive: subtotal = sum of item prices; tax = subtotal × (tax_percent/100); total = subtotal - discount + shipping + tax.
     * Uses current user's cart unless query product_id (+ optional quantity) is sent for Buy Now preview (cart can be empty).
     * Shipping and tax % from shop settings (settings table).
     */
    public function orderSummary(Request $request)
    {
        $user = $request->user();
        $preview = self::checkoutPreview($request, $user->id);
        $orderSummary = self::buildOrderSummary($preview['subtotal'], 0);
        // Ensure numeric fields for JSON (floats). Exclude tax amount from response – only tax_percent is returned.
        $orderSummary['subtotal'] = (float) $orderSummary['subtotal'];
        $orderSummary['discount'] = (float) $orderSummary['discount'];
        $orderSummary['shipping'] = (float) $orderSummary['shipping'];
        $orderSummary['tax_percent'] = (float) $orderSummary['tax_percent'];
        $orderSummary['total'] = (float) $orderSummary['total'];
        unset($orderSummary['tax']);
        return ApiResponse::success('Order summary retrieved.', $orderSummary);
    }

    /**
     * View cart (Shopping Cart screen: items + order summary).
     */
    public function view(Request $request)
    {
        $user = $request->user();

        $cartItems = Cart::where('user_id', $user->id)
            ->with(['product.category', 'product.primaryImage'])
            ->get();

        $validItems = $cartItems->filter(fn ($item) => $item->product !== null);

        $items = $validItems->map(fn ($item) => self::cartItemToFrontend($item))->values()->all();

        $subtotal = $validItems->sum(function ($item) {
            return $item->quantity * (float) $item->product->price;
        });
        $subtotal = round($subtotal, 2);
        $orderSummary = self::buildOrderSummary($subtotal, 0);

        return ApiResponse::success('Cart retrieved successfully.', [
            'items' => $items,
            'order_summary' => $orderSummary,
        ]);
    }

    /**
     * Update cart item quantity (Shopping Cart +/- controls).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cartItem = Cart::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        $cartItem->load(['product.category', 'product.primaryImage']);
        $data = self::cartItemToFrontend($cartItem);

        return ApiResponse::success('Cart item updated.', $data);
    }

    /**
     * Remove item from cart (Shopping Cart trash icon).
     */
    public function remove(Request $request, $id)
    {
        $user = $request->user();
        $cartItem = Cart::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->delete();

        return ApiResponse::success('Item removed from cart.');
    }
}
