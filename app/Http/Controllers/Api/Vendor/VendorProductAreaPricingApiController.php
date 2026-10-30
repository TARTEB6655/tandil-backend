<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\ProductAreaPricing;
use App\Support\ServiceAreaPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor per-service product area pricing (list → edit one product).
 *
 * GET  /api/vendor/products/area-pricing
 * GET  /api/vendor/products/{productId}/area-pricing
 * PUT|POST /api/vendor/products/{productId}/area-pricing
 */
class VendorProductAreaPricingApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        $items = Product::query()
            ->where('vendor_id', $vendor->id)
            ->where('type', 'service')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => ProductAreaPricing::vendorListItem($product))
            ->values()
            ->all();

        return ApiResponse::success('Service products for area pricing retrieved.', [
            'screen_title' => 'Service products',
            'screen_description' => 'Tap a service to set its price per m² (and optional fixed price) for the customer app.',
            'items' => $items,
        ]);
    }

    public function show(Request $request, int $productId): JsonResponse
    {
        $vendor = $this->vendor($request);
        $product = $this->serviceProductForVendor($vendor, $productId);

        return ApiResponse::success(
            'Product area pricing retrieved.',
            ProductAreaPricing::vendorManagePayload($product)
        );
    }

    public function update(Request $request, int $productId): JsonResponse
    {
        $vendor = $this->vendor($request);
        $product = $this->serviceProductForVendor($vendor, $productId);

        ProductAreaPricing::hydrateSaveRequest($request, $product);
        $this->normalizeIncludes($request);

        $validated = $request->validate([
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

        return ApiResponse::success(
            'Area pricing saved for this service.',
            ProductAreaPricing::vendorManagePayload($product->fresh())
        );
    }

    private function vendor(Request $request): Vendor
    {
        $vendor = $request->attributes->get('vendor');
        if (! $vendor instanceof Vendor) {
            abort(403, 'Vendor account required.');
        }

        return $vendor;
    }

    private function serviceProductForVendor(Vendor $vendor, int $productId): Product
    {
        $product = Product::query()
            ->where('id', $productId)
            ->where('vendor_id', $vendor->id)
            ->where('type', 'service')
            ->first();

        if ($product === null) {
            abort(404, 'Service product not found for this vendor.');
        }

        return $product;
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
