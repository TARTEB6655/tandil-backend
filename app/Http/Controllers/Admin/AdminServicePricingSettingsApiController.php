<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ServiceAreaPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Global Admin Setting for service pricing (Fixed vs per m²).
 * No service_id / product_id — applies to ALL services.
 * Instant Order Fee remains for shop/category product checkout.
 *
 * GET/PUT/POST /api/admin/settings/service-pricing
 * Form-data supported on PUT/POST.
 */
class AdminServicePricingSettingsApiController extends Controller
{
    /**
     * GET /api/admin/settings/service-pricing
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Service pricing settings retrieved.',
            'data' => ServiceAreaPricing::globalAdminApiPayload(),
        ]);
    }

    /**
     * PUT|POST /api/admin/settings/service-pricing
     *
     * Form-data:
     * - pricing_type: fixed | per_m2
     * - price: 70
     * - price_includes[materials]: 1
     * - ...
     */
    public function update(Request $request): JsonResponse
    {
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

        $type = ServiceAreaPricing::normalizeType($validated['pricing_type'], true);
        if (! in_array($type, [ServiceAreaPricing::TYPE_FIXED, ServiceAreaPricing::TYPE_PER_M2], true)) {
            throw ValidationException::withMessages([
                'pricing_type' => ['Pricing type must be fixed or per_m2.'],
            ]);
        }

        $sync = ServiceAreaPricing::saveGlobal(
            $type,
            (float) $validated['price'],
            $request->input('price_includes')
        );

        $payload = ServiceAreaPricing::globalAdminApiPayload();
        $payload['synced_services'] = $sync['synced_services'];
        $payload['synced_products'] = $sync['synced_products'];

        return response()->json([
            'success' => true,
            'message' => 'Service pricing settings updated for all services.',
            'data' => $payload,
        ]);
    }

    private function normalizeFormData(Request $request): void
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
