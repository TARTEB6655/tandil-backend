<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin UI screen: tree / palm unit pricing + Price includes.
 * Matches the Product Settings screenshot only — no fixed / per_m2 mix.
 *
 * GET/PUT/POST /api/admin/settings/tree-palm-pricing
 */
class AdminServiceTreePalmSettingsApiController extends Controller
{
    /**
     * GET /api/admin/settings/tree-palm-pricing
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tree / palm pricing settings retrieved.',
            'data' => ServiceTreePricing::adminUiPayload(),
        ]);
    }

    /**
     * PUT|POST /api/admin/settings/tree-palm-pricing
     *
     * Form-data / JSON — UI fields only:
     * - show_tree_options: 1|0
     * - price_per_tree: 50
     * - price_per_palm_tree: 80
     * - price_includes[materials|installation|labor|transportation|delivery]: 0|1
     */
    public function update(Request $request): JsonResponse
    {
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

        ServiceTreePricing::saveGlobal(
            (bool) $validated['show_tree_options'],
            (float) ($validated['price_per_tree'] ?? 0),
            (float) ($validated['price_per_palm_tree'] ?? 0)
        );

        if ($request->has('price_includes')) {
            ServiceAreaPricing::savePriceIncludesOnly($request->input('price_includes'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Tree / palm pricing settings updated.',
            'data' => ServiceTreePricing::adminUiPayload(),
        ]);
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
