<?php

namespace App\Services;

use App\Http\Controllers\Shop\CartController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\UserAddress;
use Illuminate\Http\Request;

/**
 * Builds shop orders for checkout (guest or authenticated). Used by Stripe / PayPal flows.
 */
class ShopCheckoutOrderService
{
    public function normalizeCheckoutRequest(Request $request): void
    {
        $all = $request->all();
        $shipping = $all['shipping_address'] ?? null;
        if (is_array($shipping)) {
            $all['full_name'] = $shipping['fullName'] ?? $shipping['full_name'] ?? $all['full_name'] ?? null;
            $all['phone_number'] = $shipping['phone'] ?? $shipping['phone_number'] ?? $all['phone_number'] ?? null;
            $all['street_address'] = $shipping['street'] ?? $shipping['street_address'] ?? $all['street_address'] ?? null;
            $all['city'] = $shipping['city'] ?? $all['city'] ?? null;
            $all['state'] = $shipping['state'] ?? $all['state'] ?? null;
            $all['zip_code'] = $shipping['zipCode'] ?? $shipping['zip_code'] ?? $all['zip_code'] ?? null;
            $all['country'] = $shipping['country'] ?? $all['country'] ?? null;
        }
        $request->merge($all);
    }

    public function createGuestOrder(Request $request, string $paymentMethod): ?Order
    {
        $items = $request->input('items');
        $subtotal = 0;
        foreach ($items as $item) {
            $p = \App\Models\Product::find($item['product_id'] ?? null);
            if ($p) {
                $subtotal += (float) $p->price * (int) ($item['qty'] ?? 1);
            }
        }
        $subtotal = round($subtotal, 2);
        $summary = CartController::buildOrderSummary($subtotal);
        $total = $summary['total'];
        $shippingAmount = $summary['shipping'];
        $taxAmount = $summary['tax'];
        $taxPercent = $summary['tax_percent'];

        $order = Order::create([
            'user_id' => null,
            'guest_email' => $request->input('email'),
            'guest_full_name' => $request->input('full_name'),
            'guest_phone' => $request->input('phone_number'),
            'guest_street_address' => $request->input('street_address'),
            'guest_city' => $request->input('city'),
            'guest_state' => $request->input('state'),
            'guest_zip_code' => $request->input('zip_code'),
            'guest_country' => $request->input('country'),
            'shipping_address_id' => null,
            'total_amount' => $total,
            'subtotal_amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_percent' => $taxPercent,
            'shipping_amount' => $shippingAmount,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        foreach ($items as $item) {
            $product = \App\Models\Product::find($item['product_id'] ?? null);
            if ($product) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['qty'] ?? 1,
                    'price' => $product->price,
                    'subtotal' => $product->price * ($item['qty'] ?? 1),
                ]);
            }
        }

        return $order;
    }

    public function createLoggedInOrder(Request $request, string $paymentMethod): ?Order
    {
        $user = $request->user();
        $addressId = $request->input('address_id');
        $items = $request->input('items', []);

        if ($addressId) {
            $address = UserAddress::where('user_id', $user->id)->find((int) $addressId);
            if (! $address) {
                return null;
            }
        } else {
            $address = new UserAddress;
            $address->user_id = $user->id;
            $address->full_name = $request->input('full_name');
            $address->phone_number = $request->input('phone_number');
            $address->street_address = $request->input('street_address');
            $address->city = $request->input('city');
            $address->state = $request->input('state');
            $address->zip_code = $request->input('zip_code');
            $address->country = $request->input('country');
            $address->is_default = false;
            $address->save();
        }

        if (empty($items)) {
            $cartItems = Cart::where('user_id', $user->id)->with('product')->get();
            $validCart = $cartItems->filter(fn ($c) => $c->product !== null);
            if ($validCart->isEmpty()) {
                return null;
            }
            $subtotal = round($validCart->sum(fn ($c) => $c->quantity * (float) $c->product->price), 2);
            foreach ($validCart as $c) {
                $items[] = ['product_id' => $c->product_id, 'qty' => $c->quantity];
            }
        } else {
            $subtotal = 0;
            foreach ($items as $item) {
                $p = \App\Models\Product::find($item['product_id'] ?? null);
                if ($p) {
                    $subtotal += (float) $p->price * (int) ($item['qty'] ?? 1);
                }
            }
            $subtotal = round($subtotal, 2);
        }

        $summary = CartController::buildOrderSummary($subtotal);
        $total = $summary['total'];
        $shippingAmount = $summary['shipping'];
        $taxAmount = $summary['tax'];
        $taxPercent = $summary['tax_percent'];

        $order = Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'total_amount' => $total,
            'subtotal_amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_percent' => $taxPercent,
            'shipping_amount' => $shippingAmount,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        foreach ($items as $item) {
            $product = \App\Models\Product::find($item['product_id'] ?? null);
            if ($product) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['qty'] ?? 1,
                    'price' => $product->price,
                    'subtotal' => $product->price * ($item['qty'] ?? 1),
                ]);
            }
        }

        if (empty($request->input('items'))) {
            Cart::where('user_id', $user->id)->delete();
        }

        return $order;
    }
}
