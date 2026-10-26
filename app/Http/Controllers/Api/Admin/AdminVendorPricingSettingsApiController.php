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
 * Admin Product Settings — Vendor pricing (list + manage per vendor).
 *
 * GET  /api/admin/vendors/pricing
 * GET  /api/admin/vendors/{id}/pricing
 * PUT|POST /api/admin/vendors/{id}/pricing
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

    public function show(int $id): JsonResponse
    {
        $vendor = $this->managedVendor($id);
        $vendor->loadMissing(['profile', 'user']);

        return ApiResponse::success(
            'Vendor pricing settings retrieved.',
            $this->managePayload($vendor)
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = $this->managedVendor($id);
        $this->normalizeServiceFormData($request);
        ServiceTreePricing::normalizeAdminFormData($request);
        $this->normalizeTreeIncludes($request);

        $validated = $request->validate([
            'pricing_type' => 'required|in:fixed,per_m2',
            'price' => 'required|numeric|min:0',
            'price_includes' => 'nullable',
            'price_includes.materials' => 'nullable|boolean',
            'price_includes.installation' => 'nullable|boolean',
            'price_includes.labor' => 'nullable|boolean',
            'price_includes.transportation' => 'nullable|boolean',
            'price_includes.delivery' => 'nullable|boolean',
            'show_tree_options' => 'required|boolean',
            'price_per_tree' => 'nullable|numeric|min:0',
            'price_per_palm_tree' => 'nullable|numeric|min:0',
        ], [
            'pricing_type.required' => 'Select Fixed Price or Price per Square Meter (m²).',
            'pricing_type.in' => 'Pricing type must be fixed or per_m2.',
            'price.required' => 'Enter the price amount.',
            'show_tree_options.required' => 'Set Show on customer service products on or off.',
        ]);

        $type = ServiceAreaPricing::normalizeType($validated['pricing_type'], true);
        if (! in_array($type, [ServiceAreaPricing::TYPE_FIXED, ServiceAreaPricing::TYPE_PER_M2], true)) {
            throw ValidationException::withMessages([
                'pricing_type' => ['Pricing type must be fixed or per_m2.'],
            ]);
        }

        $sync = ServiceAreaPricing::saveVendor(
            $vendor->id,
            $type,
            (float) $validated['price'],
            $request->input('price_includes')
        );

        ServiceTreePricing::saveVendor(
            $vendor->id,
            (bool) $validated['show_tree_options'],
            (float) ($validated['price_per_tree'] ?? 0),
            (float) ($validated['price_per_palm_tree'] ?? 0)
        );

        $payload = $this->managePayload($vendor);
        $payload['service_pricing']['synced_services'] = $sync['synced_services'];
        $payload['service_pricing']['synced_products'] = $sync['synced_products'];

        return ApiResponse::success('Vendor pricing settings updated.', $payload);
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
    private function managePayload(Vendor $vendor): array
    {
        $profile = $vendor->profile;
        $configured = ServiceAreaPricing::vendorIsConfigured($vendor->id);

        return [
            'screen_title' => 'Manage vendor pricing',
            'vendor' => [
                'id' => $vendor->id,
                'vendor_id' => $vendor->id,
                'company_name' => $profile?->business_name,
                'business_name' => $profile?->business_name,
                'vendor_name' => $profile?->owner_name,
                'owner_name' => $profile?->owner_name,
                'email' => $profile?->email ?? $vendor->user?->email,
                'logo_url' => $vendor->profile?->logo_url ?? $vendor->logo_url,
            ],
            'pricing_configured' => $configured,
            'pricing_status_label' => $configured ? null : 'No vendor pricing set yet',
            'service_pricing' => ServiceAreaPricing::vendorAdminApiPayload($vendor->id),
            'tree_palm_pricing' => ServiceTreePricing::vendorUiPayload($vendor->id),
        ];
    }

    private function normalizeServiceFormData(Request $request): void
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

    private function normalizeTreeIncludes(Request $request): void
    {
        if ($request->has('price_includes') && is_string($request->input('price_includes'))) {
            return;
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
