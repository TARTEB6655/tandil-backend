<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CartController as ShopCartController;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductOption;
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

        $subtotal = round($validItems->sum(function ($item) {
            $unit = $item->unit_price ?? (float) $item->product->price;
            return $item->quantity * $unit;
        }), 2);

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
            'option_ids' => 'nullable|array',
            'option_ids.*' => 'integer|exists:product_options,id',
        ]);

        $user = Auth::user();
        $product = Product::with('optionGroups.options')->findOrFail($request->product_id);

        $selectedOptionIds = collect($request->input('option_ids', []))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        // Validate variable-product required groups and calculate option-based price.
        $unitPrice = (float) $product->price;
        if (($product->product_type ?? 'simple') === 'variable') {
            $productOptionIds = $product->optionGroups
                ->flatMap(fn ($g) => $g->options->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->values();

            $invalidOptionIds = $selectedOptionIds->diff($productOptionIds);
            if ($invalidOptionIds->isNotEmpty()) {
                return back()->with('error', 'Invalid option selected for this product.');
            }

            foreach ($product->optionGroups as $group) {
                $groupOptionIds = $group->options->pluck('id')->map(fn ($id) => (int) $id);
                $selectedInGroup = $selectedOptionIds->intersect($groupOptionIds)->values();

                if ($group->is_required && $selectedInGroup->isEmpty()) {
                    return back()->with('error', "Please select required option(s) for {$group->name}.");
                }
                if ($group->input_type === 'single' && $selectedInGroup->count() > 1) {
                    return back()->with('error', "Only one option can be selected for {$group->name}.");
                }
            }

            if ($selectedOptionIds->isNotEmpty()) {
                $modifier = (float) ProductOption::whereIn('id', $selectedOptionIds->all())->sum('price_modifier');
                $unitPrice = max(0, round(((float) $product->price) + $modifier, 2));
            }
        }

        // Check stock
        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Insufficient stock available.');
        }

        // Check if item already in cart
        $selectedOptionsNormalized = $selectedOptionIds->sort()->values()->all();
        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->get()
            ->first(function (Cart $row) use ($selectedOptionsNormalized) {
                $current = collect($row->selected_options ?? [])->map(fn ($v) => (int) $v)->sort()->values()->all();
                return $current === $selectedOptionsNormalized;
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

