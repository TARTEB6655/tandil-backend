<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public const CURRENCY = 'AED';

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
        $discount = 0;
        $shipping = (float) config('shop.shipping_amount', 9.99);
        $total = round($subtotal - $discount + $shipping, 2);

        $orderSummary = [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $total,
            'currency' => self::CURRENCY,
        ];

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
