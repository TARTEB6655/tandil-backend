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
            ->with(['product.category', 'product.optionGroups.options'])
            ->get();

        $removedNames = [];
        $validItems = $cartItems->filter(function (Cart $item) use (&$removedNames) {
            if ($item->product === null) {
                return false;
            }
            if (! Cart::cartLineIsComplete($item)) {
                $removedNames[] = $item->product->name;
                $item->delete();

                return false;
            }

            return true;
        });

        if ($removedNames !== []) {
            session()->flash(
                'error',
                'Removed from cart (required options missing): '.implode(', ', array_unique($removedNames)).'. Please open the product and select all required options.'
            );
        }

        $subtotal = round($validItems->sum(fn (Cart $item) => $item->quantity * $item->lineUnitPrice()), 2);

        // Use same order summary as API (admin settings: tax %, shipping)
        $orderSummary = ShopCartController::buildOrderSummary($subtotal, 0, $cartItems);
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
            'categoryShippingBreakdown' => $orderSummary['category_shipping_breakdown'] ?? [],
            'categoryTaxBreakdown' => $orderSummary['category_tax_breakdown'] ?? [],
            'usesCategoryTax' => (bool) ($orderSummary['uses_category_tax'] ?? false),
        ]);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
            'option_ids' => 'nullable|array',
            'option_ids.*' => 'integer|exists:product_options,id',
            'selected_option_ids' => 'nullable|array',
            'selected_option_ids.*' => 'integer|exists:product_options,id',
        ]);

        $user = Auth::user();
        $product = Product::with('optionGroups.options')->findOrFail($request->product_id);

        $selectedOptionsNormalized = \App\Http\Controllers\Shop\CartController::selectedOptionIdsFromRequest($request);
        $optionError = Cart::validateSelectedOptionsMessage($product, $selectedOptionsNormalized);
        if ($optionError !== null) {
            return back()->with('error', $optionError);
        }

        $unitPrice = Cart::calculateUnitPrice($product, $selectedOptionsNormalized);

        // Check stock
        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Insufficient stock available.');
        }

        // Check if item already in cart (same product + same option set)
        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->get()
            ->first(function (Cart $row) use ($selectedOptionsNormalized) {
                return Cart::normalizeSelectedOptionIds($row->selected_options) === $selectedOptionsNormalized;
            });

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;
            if ($product->stock < $newQuantity) {
                return back()->with('error', 'Cannot add more items. Stock limit reached.');
            }
            $cartItem->quantity = $newQuantity;
            $cartItem->unit_price = $unitPrice;
            $cartItem->selected_options = $selectedOptionsNormalized;
            $cartItem->save();
        } else {
            Cart::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'selected_options' => $selectedOptionsNormalized,
                'unit_price' => $unitPrice,
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

