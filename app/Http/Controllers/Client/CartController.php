<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CartController as ShopCartController;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $cartItems = Cart::where('user_id', $user->id)
            ->with('product.category')
            ->get();
        $validItems = $cartItems->filter(fn ($item) => $item->product !== null);

        $subtotal = round($validItems->sum(fn ($item) => $item->quantity * (float) $item->product->price), 2);

        // Use same order summary as API (admin settings: tax %, shipping)
        $orderSummary = ShopCartController::buildOrderSummary($subtotal, 0);
        $tax = $orderSummary['tax'];
        $shipping = $orderSummary['shipping'];
        $total = $orderSummary['total'];
        $taxPercent = $orderSummary['tax_percent'];
        $shippingLabel = $orderSummary['shipping_label'];

        return view('client.cart.index', [
            'cartItems' => $validItems->values(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'taxPercent' => $taxPercent,
            'shippingLabel' => $shippingLabel,
        ]);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        // Check stock
        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Insufficient stock available.');
        }

        // Check if item already in cart
        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;
            if ($product->stock < $newQuantity) {
                return back()->with('error', 'Cannot add more items. Stock limit reached.');
            }
            $cartItem->quantity = $newQuantity;
            $cartItem->save();
        } else {
            Cart::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        return back()->with('success', 'Product added to cart successfully!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $cartItem = Cart::where('user_id', $user->id)
            ->where('id', $id)
            ->with('product')
            ->firstOrFail();

        if ($cartItem->product->stock < $request->quantity) {
            return back()->with('error', 'Insufficient stock available.');
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return back()->with('success', 'Cart updated successfully!');
    }

    public function remove($id)
    {
        $user = Auth::user();
        $cartItem = Cart::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->delete();

        return back()->with('success', 'Item removed from cart.');
    }

    public function clear()
    {
        $user = Auth::user();
        Cart::where('user_id', $user->id)->delete();

        return redirect()->route('client.cart.index')->with('success', 'Cart cleared successfully.');
    }
}

