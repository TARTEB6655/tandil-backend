<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Product;
use App\Support\ProductAreaPricing;
use App\Support\ServiceAreaPricing;
use Illuminate\Http\Request;

trait HandlesProductAreaPricingSave
{
    /**
     * @return array<string, mixed>
     */
    protected function validateProductAreaPricingSave(Request $request, Product $product): array
    {
        ProductAreaPricing::hydrateSaveRequest($request, $product);
        $this->normalizeProductAreaPricingIncludes($request);

        return $request->validate([
            'show_on_client_products_services' => 'required|boolean',
            'enable_fixed_price' => 'required|boolean',
            'enable_per_m2' => 'required|boolean',
            'require_area_before_checkout' => 'nullable|boolean',
            'fixed_price' => 'required|numeric|min:0',
            'per_m2_price' => 'required|numeric|min:0',
            'price_includes' => 'nullable',
            'price_includes.materials' => 'nullable|boolean',
            'price_includes.installation' => 'nullable|boolean',
            'price_includes.labor' => 'nullable|boolean',
            'price_includes.transportation' => 'nullable|boolean',
            'price_includes.delivery' => 'nullable|boolean',
        ], [
            'show_on_client_products_services.required' => 'Set Show on client product detail on or off.',
        ]);
    }

    protected function persistProductAreaPricing(Request $request, Product $product, array $validated): Product
    {
        $parsed = ProductAreaPricing::parseSaveInput($validated, $product);

        ProductAreaPricing::save(
            $product,
            $parsed['enable_fixed'],
            $parsed['enable_per_m2'],
            $parsed['fixed_price'],
            $parsed['per_m2_price'],
            $request->input('price_includes'),
            $parsed['show_on_client_products_services'],
            $parsed['require_area_before_checkout']
        );

        return $product->fresh();
    }

    private function normalizeProductAreaPricingIncludes(Request $request): void
    {
        if ($request->has('price_includes') && is_string($request->input('price_includes'))) {
            $decoded = json_decode($request->input('price_includes'), true);
            if (is_array($decoded)) {
                $request->merge(['price_includes' => $decoded]);
            }
        }

        $includes = is_array($request->input('price_includes'))
            ? $request->input('price_includes')
            : [];

        foreach (ServiceAreaPricing::INCLUDE_KEYS as $key) {
            foreach (["price_includes[{$key}]", "price_includes_{$key}", "includes_{$key}"] as $flatKey) {
                if ($request->has($flatKey) && ! array_key_exists($key, $includes)) {
                    $includes[$key] = $request->input($flatKey);
                }
            }
        }

        if ($includes !== [] || is_array($request->input('price_includes'))) {
            $request->merge([
                'price_includes' => ServiceAreaPricing::normalizeIncludes(
                    $includes !== [] ? $includes : $request->input('price_includes'),
                    true
                ),
            ]);
        }
    }
}
