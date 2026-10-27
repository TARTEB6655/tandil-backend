<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\VendorStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\Vendor\AdminVendorMobileService;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin Product Settings — Vendor pricing (list + two manage APIs, same shape as vendor app).
 *
 * GET  /api/admin/vendors/pricing
 * GET|PUT|POST /api/admin/vendors/{id}/pricing/service
 * GET|PUT|POST /api/admin/vendors/{id}/pricing/tree-palm
 */
class AdminVendorPricingSettingsApiController extends Controller
{
    public function __construct(
        private readonly AdminVendorMobileService $mobile
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->query('q') && ! $request->query('search')) {
            $request->merge(['search' => $request->query('q')]);
        }

        return ApiResponse::success(
            'Vendor pricing list retrieved.',
            $this->mobile->pricingListIndex($request)
        );
    }

    /**
     * Area-based pricing — same fields as GET /api/vendor/settings/service-pricing.
     */
    public function showServicePricing(int $id): JsonResponse
    {
        $vendor = $this->managedVendor($id);

        return ApiResponse::success(
            'Vendor service pricing settings retrieved.',
            $this->servicePricingPayload($vendor)
        );
    }

    public function updateServicePricing(Request $request, int $id): JsonResponse
    {
        $vendor = $this->managedVendor($id);
        $this->normalizeServiceFormData($request);

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

        $payload = $this->servicePricingPayload($vendor);
        $payload['synced_services'] = $sync['synced_services'];
        $payload['synced_products'] = $sync['synced_products'];

        return ApiResponse::success('Vendor service pricing settings updated.', $payload);
    }

    /**
     * Tree / palm pricing — same fields as GET /api/vendor/settings/tree-palm-pricing.
     */
    public function showTreePalmPricing(int $id): JsonResponse
    {
        $vendor = $this->managedVendor($id);

        return ApiResponse::success(
            'Vendor tree / palm pricing settings retrieved.',
            $this->treePalmPricingPayload($vendor)
        );
    }

    public function updateTreePalmPricing(Request $request, int $id): JsonResponse
    {
        $vendor = $this->managedVendor($id);
        ServiceTreePricing::normalizeAdminFormData($request);
        $this->normalizeTreeIncludes($request);

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
            'show_tree_options.required' => 'Set Show on client products & services on or off.',
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

        return ApiResponse::success(
            'Vendor tree / palm pricing settings updated.',
            $this->treePalmPricingPayload($vendor)
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

    /**
     * @return array<string, mixed>
     */
    private function vendorCard(Vendor $vendor): array
    {
        $vendor->loadMissing(['profile', 'user']);
        $profile = $vendor->profile;

        return [
            'id' => $vendor->id,
            'vendor_id' => $vendor->id,
            'company_name' => $profile?->business_name,
            'business_name' => $profile?->business_name,
            'vendor_name' => $profile?->owner_name,
            'owner_name' => $profile?->owner_name,
            'email' => $profile?->email ?? $vendor->user?->email,
            'logo_url' => $vendor->profile?->logo_url ?? $vendor->logo_url,
        ];
    }

    /**
     * Flat payload — vendor card + all service-pricing UI fields at data root (like vendor API).
     *
     * @return array<string, mixed>
     */
    private function servicePricingPayload(Vendor $vendor): array
    {
        $configured = ServiceAreaPricing::vendorIsConfigured($vendor->id);

        return array_merge(
            [
                'screen_title' => 'Manage vendor pricing',
                'screen_section' => 'Area-Based Pricing',
                'screen_section_description' => 'Applies to this vendor’s service products. Choose Fixed Price or Price per m².',
                'vendor' => $this->vendorCard($vendor),
                'pricing_configured' => $configured,
                'pricing_status_label' => $configured ? null : 'No vendor pricing set yet',
            ],
            ServiceAreaPricing::vendorAdminApiPayload($vendor->id)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function treePalmPricingPayload(Vendor $vendor): array
    {
        return array_merge(
            [
                'screen_title' => 'Manage vendor pricing',
                'screen_section' => 'Price per Tree & Palm',
                'screen_section_description' => 'Set unit prices for trees and/or palm trees. Leave a field empty to hide that counter on the customer app.',
                'vendor' => $this->vendorCard($vendor),
            ],
            ServiceTreePricing::vendorUiPayload($vendor->id)
        );
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

    private function normalizeServiceFormData(Request $request): void
    {
        $this->normalizeMultiselectFlags($request);

        if ($request->has('show_on_client_products_services') && is_string($request->input('show_on_client_products_services'))) {
            $request->merge([
                'show_on_client_products_services' => filter_var($request->input('show_on_client_products_services'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
        foreach (['show_area_pricing_on_client', 'show_on_customer_service_products'] as $key) {
            if ($request->has($key) && ! $request->has('show_on_client_products_services')) {
                $request->merge([
                    'show_on_client_products_services' => filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN),
                ]);
                break;
            }
        }

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

    private function normalizeTreeIncludes(Request $request): void
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
