<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shop settings for client: GET (public) and PUT (client auth).
 * Same data as admin GET/PUT /api/admin/settings/shop.
 */
class ShopSettingsController extends Controller
{
    /**
     * GET /api/shop/settings
     * Returns shipping_amount, tax_percent, currency for cart/checkout display.
     * No auth required (public).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->getShopSummary(),
        ]);
    }

    /**
     * PUT /api/shop/settings
     * Client can update shop settings (shipping_amount, tax_percent). Auth: client role.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_amount' => 'nullable|numeric|min:0',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($request->has('shipping_amount')) {
            Setting::set('shop_shipping_amount', (string) $request->input('shipping_amount'), 'text', 'shop');
        }
        if ($request->has('tax_percent')) {
            Setting::set('shop_tax_percent', (string) $request->input('tax_percent'), 'text', 'shop');
        }

        return response()->json([
            'success' => true,
            'data' => $this->getShopSummary(),
        ]);
    }

    protected function getShopSummary(): array
    {
        $shipping = Setting::get('shop_shipping_amount');
        $tax = Setting::get('shop_tax_percent');

        return [
            'shipping_amount' => (float) ($shipping !== null && $shipping !== '' ? $shipping : config('shop.shipping_amount', 0)),
            'tax_percent' => (float) ($tax !== null && $tax !== '' ? $tax : config('shop.tax_percent', 5)),
            'currency' => config('shop.currency', 'AED'),
            // Store checkout has no backend minimum-order gate (enforce in app only if needed).
            'minimum_order_amount' => 0.0,
        ];
    }
}
