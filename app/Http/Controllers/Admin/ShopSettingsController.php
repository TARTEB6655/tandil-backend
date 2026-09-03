<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CartController;
use App\Models\Setting;
use App\Services\CategoryShippingService;
use App\Support\InstantOrderFee;
use Illuminate\Http\Request;

class ShopSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $globalShipping = CartController::getEffectiveShippingAmount();
        $taxPercent = CartController::getEffectiveTaxPercent();
        $categoryRates = CategoryShippingService::allCategoryRatesForAdmin();

        return view('admin.shop-settings.index', [
            'globalShipping' => $globalShipping,
            'taxPercent' => $taxPercent,
            'instantOrderFee' => InstantOrderFee::amount(),
            'currency' => config('shop.currency', 'AED'),
            'categoryRates' => $categoryRates,
        ]);
    }

    public function updateGlobal(Request $request)
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

        return redirect()
            ->route('admin.shop-settings.index')
            ->with('success', 'Global shop settings saved.');
    }

    public function updateInstantOrderFee(Request $request)
    {
        $request->validate([
            'instant_order_fee_amount' => 'required|numeric|min:0',
            'instant_order_fee_enabled' => 'nullable|boolean',
        ]);

        InstantOrderFee::saveFromRequest($request->all());

        return redirect()
            ->route('admin.shop-settings.index')
            ->with('success', 'Instant order fee saved.');
    }

    public function updateCategoryShipping(Request $request)
    {
        $request->validate([
            'rates' => 'required|array',
            'rates.*.category_id' => 'required|integer|exists:categories,id',
            'rates.*.shipping_amount' => 'nullable|numeric|min:0',
            'rates.*.shipping_cost' => 'nullable|numeric|min:0',
            'rates.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $rates = [];
        foreach ($request->input('rates', []) as $row) {
            $amount = $row['shipping_cost'] ?? $row['shipping_amount'] ?? null;
            if ($amount === '' || $amount === null) {
                $amount = null;
            }
            $rates[] = [
                'category_id' => (int) $row['category_id'],
                'shipping_cost' => $amount,
                'tax_percentage' => $row['tax_percentage'] ?? null,
            ];
        }

        CategoryShippingService::syncAdminRates($rates);

        return redirect()
            ->route('admin.shop-settings.index')
            ->with('success', 'Category delivery fees saved.');
    }
}
