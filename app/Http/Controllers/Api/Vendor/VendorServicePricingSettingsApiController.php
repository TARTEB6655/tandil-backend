<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Support\ServiceAreaPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Vendor-scoped service pricing (Fixed vs per m²) — mirrors admin global settings.
 *
 * GET/PUT/POST /api/vendor/settings/service-pricing
 */
class VendorServicePricingSettingsApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        return response()->json([
            'success' => true,
            'message' => 'Service pricing settings retrieved.',
            'data' => ServiceAreaPricing::vendorAdminApiPayload($vendor->id),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        $this->normalizeFormData($request, $vendor->id);

        $this->normalizeClientVisibility($request);

        $this->normalizeMultiselectFlags($request);

        ServiceAreaPricing::hydrateVendorServicePricingRequest($request, $vendor->id);

        $usesMultiselect = $request->has('enable_fixed_price')
            || $request->has('enable_per_m2')
            || $request->has('fixed_price')
            || $request->has('per_m2_price')
            || $request->has('price_per_m2');

        if ($usesMultiselect) {
            $validated = $request->validate([
                'enable_fixed_price' => 'required|boolean',
                'enable_per_m2' => 'required|boolean',
                'fixed_price' => 'required|numeric|min:0',
                'per_m2_price' => 'required|numeric|min:0',
                'show_on_client_products_services' => 'required|boolean',
                'price_includes' => 'nullable',
                'price_includes.materials' => 'nullable|boolean',
                'price_includes.installation' => 'nullable|boolean',
                'price_includes.labor' => 'nullable|boolean',
                'price_includes.transportation' => 'nullable|boolean',
                'price_includes.delivery' => 'nullable|boolean',
            ], [
                'show_on_client_products_services.required' => 'Set Show on client products & services on or off.',
            ]);
        } else {
            $validated = $request->validate([
                'pricing_type' => 'required|in:fixed,per_m2',
                'price' => 'required|numeric|min:0',
                'show_on_client_products_services' => 'required|boolean',
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
                'show_on_client_products_services.required' => 'Set Show on client products & services on or off.',
            ]);
        }

        $parsed = ServiceAreaPricing::parseVendorServicePricingSaveInput($validated, $vendor->id);

        $sync = ServiceAreaPricing::saveVendorMultiselect(
            $vendor->id,
            $parsed['enable_fixed'],
            $parsed['enable_per_m2'],
            $parsed['fixed_price'],
            $parsed['per_m2_price'],
            $request->input('price_includes'),
            $parsed['show_on_client_products_services']
        );

        $payload = ServiceAreaPricing::vendorAdminApiPayload($vendor->id);
        $payload['synced_services'] = $sync['synced_services'];
        $payload['synced_products'] = $sync['synced_products'];

        return response()->json([
            'success' => true,
            'message' => 'Service pricing settings updated for your service products.',
            'data' => $payload,
        ]);
    }

    private function vendor(Request $request): Vendor
    {
        $vendor = $request->attributes->get('vendor');
        if (! $vendor instanceof Vendor) {
            abort(403, 'Vendor account required.');
        }

        return $vendor;
    }

    private function normalizeClientVisibility(Request $request): void
    {
        foreach (['show_on_client_products_services', 'show_on_customer_service_products', 'show_area_pricing_on_client'] as $key) {
            if ($request->has($key)) {
                $request->merge([
                    'show_on_client_products_services' => filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN),
                ]);
                break;
            }
        }
    }

    private function normalizeMultiselectFlags(Request $request): void
    {
        foreach (['enable_fixed_price', 'enable_per_m2'] as $key) {
            if ($request->has($key)) {
                $request->merge([
                    $key => filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }

    private function normalizeFormData(Request $request, ?int $vendorId = null): void
    {
        $this->normalizeClientVisibility($request);
        $this->normalizeMultiselectFlags($request);

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
            foreach (["price_includes[{$key}]", "price_includes_{$key}", "includes_{$key}", $key] as $flatKey) {
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
