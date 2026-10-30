<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Vendor;
use App\Http\Controllers\Api\Concerns\HandlesProductAreaPricingSave;
use App\Support\ProductAreaPricing;
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
    use HandlesProductAreaPricingSave;

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

        $validated = $this->validateProductAreaPricingSave($request, $product);
        $product = $this->persistProductAreaPricing($request, $product, $validated);

        return ApiResponse::success(
            'Area pricing saved for this service.',
            ProductAreaPricing::vendorManagePayload($product)
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
}
