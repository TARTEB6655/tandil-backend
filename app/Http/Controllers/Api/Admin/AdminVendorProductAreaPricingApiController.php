<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\VendorStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\Concerns\HandlesProductAreaPricingSave;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Vendor\AdminVendorMobileService;
use App\Support\ProductAreaPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin per-service product area pricing (same as vendor 08c, scoped by vendor_id).
 *
 * GET  /api/admin/vendors/area-pricing
 * GET  /api/admin/vendors/{vendorId}/services/area-pricing
 * GET  /api/admin/vendors/{vendorId}/services/{serviceId}/area-pricing
 * PUT|POST /api/admin/vendors/{vendorId}/services/{serviceId}/area-pricing
 */
class AdminVendorProductAreaPricingApiController extends Controller
{
    use HandlesProductAreaPricingSave;

    public function __construct(
        private readonly AdminVendorMobileService $mobile
    ) {}

    public function vendorIndex(Request $request): JsonResponse
    {
        if ($request->query('q') && ! $request->query('search')) {
            $request->merge(['search' => $request->query('q')]);
        }

        return ApiResponse::success(
            'Vendors for per-service area pricing retrieved.',
            $this->mobile->areaPricingVendorList($request)
        );
    }

    public function index(int $vendorId): JsonResponse
    {
        $vendor = $this->managedVendor($vendorId);

        $items = ProductAreaPricing::vendorServiceProductsQuery($vendor->id)
            ->get()
            ->map(fn (Product $product) => ProductAreaPricing::serviceAreaPricingListItem($product, $vendor->id))
            ->values()
            ->all();

        return ApiResponse::success('Services for area pricing retrieved.', [
            'vendor_id' => $vendor->id,
            'screen_title' => 'Services',
            'screen_description' => 'Tap a service to set its price per m² (and optional fixed price) for the customer app.',
            'items' => $items,
        ]);
    }

    public function show(int $vendorId, int $productId): JsonResponse
    {
        $vendor = $this->managedVendor($vendorId);
        $product = $this->serviceProductForVendor($vendor, $productId);

        return ApiResponse::success(
            'Product area pricing retrieved.',
            ProductAreaPricing::adminManagePayload($product, $vendor)
        );
    }

    public function update(Request $request, int $vendorId, int $productId): JsonResponse
    {
        $vendor = $this->managedVendor($vendorId);
        $product = $this->serviceProductForVendor($vendor, $productId);

        $validated = $this->validateProductAreaPricingSave($request, $product);
        $product = $this->persistProductAreaPricing($request, $product, $validated);

        return ApiResponse::success(
            'Area pricing saved for this service.',
            ProductAreaPricing::adminManagePayload($product, $vendor)
        );
    }

    private function managedVendor(int $id): Vendor
    {
        return Vendor::query()
            ->whereIn('status', [
                VendorStatus::Approved->value,
                VendorStatus::Suspended->value,
            ])
            ->findOrFail($id);
    }

    private function serviceProductForVendor(Vendor $vendor, int $productId): Product
    {
        return ProductAreaPricing::resolveVendorServiceProduct($vendor, $productId);
    }
}
