<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor tree / palm unit pricing — mirrors admin tree-palm screen.
 *
 * GET/PUT/POST /api/vendor/settings/tree-palm-pricing
 */
class VendorServiceTreePalmSettingsApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        return response()->json([
            'success' => true,
            'message' => 'Tree / palm pricing settings retrieved.',
            'data' => ServiceTreePricing::vendorUiPayload($vendor->id),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);
        ServiceTreePricing::normalizeAdminFormData($request);
        $this->normalizeIncludes($request);

        $validated = $request->validate([
            'show_tree_options' => 'required|boolean',
            'price_per_tree' => 'nullable|numeric|min:0',
            'price_per_palm_tree' => 'nullable|numeric|min:0',
            'price_includes' => 'nullable',
            'price_includes.materials' => 'nullable|boolean',
            'price_includes.installation' => 'nullable|boolean',
            'price_includes.labor' => 'nullable|boolean',
            'price_includes.transportation' => 'nullable|boolean',
            'price_includes.delivery' => 'nullable|boolean',
        ], [
            'show_tree_options.required' => 'Set Show on customer service products on or off.',
        ]);

        ServiceTreePricing::saveVendor(
            $vendor->id,
            (bool) $validated['show_tree_options'],
            (float) ($validated['price_per_tree'] ?? 0),
            (float) ($validated['price_per_palm_tree'] ?? 0)
        );

        if ($request->has('price_includes')) {
            ServiceAreaPricing::saveVendorPriceIncludesOnly($vendor->id, $request->input('price_includes'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Tree / palm pricing settings updated.',
            'data' => ServiceTreePricing::vendorUiPayload($vendor->id),
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

    private function normalizeIncludes(Request $request): void
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
