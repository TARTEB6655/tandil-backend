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
     * PUT /api/admin/products/{id}/settings
     * Body matches Product Settings UI.
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
}
