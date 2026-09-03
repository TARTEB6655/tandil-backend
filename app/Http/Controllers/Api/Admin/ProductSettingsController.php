<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ServiceAreaPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Dedicated Admin "Product Settings" screen APIs.
 * Pricing Type (Fixed / per m²) + Price includes — services only.
 *
 * Update accepts **form-data** (multipart) or x-www-form-urlencoded or JSON.
 * Prefer POST /settings for form-data (PUT multipart is unreliable in PHP).
 */
class ProductSettingsController extends Controller
{
    /**
     * GET /api/admin/products/{id}/settings
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::find($id);
        if ($product === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        return ApiResponse::success(
            'Product settings retrieved.',
            ServiceAreaPricing::productSettingsApi($product)
        );
    }

    /**
     * PUT|POST /api/admin/products/{id}/settings
     *
     * Form-data fields:
     * - pricing_type: fixed | per_m2
     * - price: 70
     * - price_includes[materials]: 1|0|true|false
     * - price_includes[installation]: ...
     * - price_includes[labor]: ...
     * - price_includes[transportation]: ...
     * - price_includes[delivery]: ...
     * Or single JSON string field: price_includes={"materials":true,...}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);
        if ($product === null) {
            return ApiResponse::error('Product not found.', 404);
        }

        if (($product->type ?? 'product') !== 'service') {
            return ApiResponse::error(
                'Product Settings (area pricing) are only available for services.',
                422,
                ['type' => ['This product is not a service. Convert type to service first, or use the product edit API for fixed price.']]
            );
        }

        $this->normalizeFormData($request);

        $validated = $request->validate([
            'pricing_type' => 'required|in:fixed,per_m2',
            'price' => 'required|numeric|min:0',
            'price_includes' => 'nullable',
            'price_includes.materials' => 'nullable|boolean',
            'price_includes.installation' => 'nullable|boolean',
            'price_includes.labor' => 'nullable|boolean',
            'price_includes.transportation' => 'nullable|boolean',
            'price_includes.delivery' => 'nullable|boolean',
        ], [
            'pricing_type.required' => 'Select Fixed Price or Price per Square Meter (m²).',
            'pricing_type.in' => 'Pricing type must be fixed or per_m2.',
            'price.required' => 'Enter the price amount.',
        ]);

        $pricingType = ServiceAreaPricing::normalizeType($validated['pricing_type'], true);
        if ($pricingType !== ServiceAreaPricing::TYPE_FIXED && $pricingType !== ServiceAreaPricing::TYPE_PER_M2) {
            throw ValidationException::withMessages([
                'pricing_type' => ['Pricing type must be fixed or per_m2.'],
            ]);
        }

        $product->pricing_type = $pricingType;
        $product->price = round((float) $validated['price'], 2);
        $product->price_includes = ServiceAreaPricing::normalizeIncludes(
            $request->input('price_includes'),
            true
        );
        $product->save();

        return ApiResponse::success(
            'Product settings updated.',
            ServiceAreaPricing::productSettingsApi($product->fresh())
        );
    }

    /**
     * Normalize multipart / form-urlencoded fields for Product Settings.
     */
    private function normalizeFormData(Request $request): void
    {
        // price_includes sent as JSON string in one form field
        if ($request->has('price_includes') && is_string($request->input('price_includes'))) {
            $decoded = json_decode($request->input('price_includes'), true);
            if (is_array($decoded)) {
                $request->merge(['price_includes' => $decoded]);
            }
        }

        // Flat form keys: price_includes_materials / includes_materials / materials
        $includes = is_array($request->input('price_includes'))
            ? $request->input('price_includes')
            : [];

        foreach (ServiceAreaPricing::INCLUDE_KEYS as $key) {
            foreach (["price_includes[{$key}]", "price_includes_{$key}", "includes_{$key}", $key] as $flatKey) {
                if ($request->has($flatKey) && ! array_key_exists($key, $includes)) {
                    $includes[$key] = $request->input($flatKey);
                }
            }
        }

        if ($includes !== []) {
            $normalized = [];
            foreach (ServiceAreaPricing::INCLUDE_KEYS as $key) {
                if (array_key_exists($key, $includes)) {
                    $normalized[$key] = filter_var($includes[$key], FILTER_VALIDATE_BOOLEAN);
                }
            }
            if ($normalized !== []) {
                $request->merge(['price_includes' => array_merge(
                    ServiceAreaPricing::emptyIncludes(),
                    array_merge(
                        is_array($request->input('price_includes')) ? $request->input('price_includes') : [],
                        $normalized
                    )
                )]);
            }
        }

        // Ensure nested booleans from form "1"/"0" are real bools for validation
        if (is_array($request->input('price_includes'))) {
            $cast = ServiceAreaPricing::normalizeIncludes($request->input('price_includes'), true);
            $request->merge(['price_includes' => $cast]);
        }
    }
}
