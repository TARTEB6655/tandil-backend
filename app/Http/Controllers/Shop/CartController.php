<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Add item to cart
     */
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $product = Product::findOrFail($request->product_id);

        // Check if item already in cart
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

        return ApiResponse::success('Item added to cart.', $cartItem->load('product'), 201);
    }

    /**
     * View cart
     */
    public function view(Request $request)
    {
        $user = $request->user();
        
        $cartItems = Cart::where('user_id', $user->id)
            ->with('product')
            ->get();

        $total = $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        return ApiResponse::success('Cart retrieved successfully.', [
            'items' => $cartItems,
            'total' => $total
        ]);
    }

    /**
     * Remove item from cart
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
