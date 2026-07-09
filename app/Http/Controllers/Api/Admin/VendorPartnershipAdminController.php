<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPartnershipApplication;
use App\Models\VendorPartnershipTier;
use App\Services\Vendor\VendorPartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorPartnershipAdminController extends Controller
{
    public function __construct(
        private readonly VendorPartnershipService $partnership
    ) {}

    public function indexTiers(): JsonResponse
    {
        $tiers = VendorPartnershipTier::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($tier) => $this->partnership->tierToArray($tier))
            ->all();

        return ApiResponse::success('Partnership tiers.', ['tiers' => $tiers]);
    }

    public function storeTier(Request $request): JsonResponse
    {
        $data = $this->validateTier($request);
        $tier = VendorPartnershipTier::create($data);

        return ApiResponse::success(
            'Partnership tier created.',
            ['tier' => $this->partnership->tierToArray($tier)],
            201
        );
    }

    public function showTier(int $id): JsonResponse
    {
        $tier = VendorPartnershipTier::findOrFail($id);

        return ApiResponse::success('Partnership tier.', [
            'tier' => $this->partnership->tierToArray($tier),
        ]);
    }

    public function updateTier(Request $request, int $id): JsonResponse
    {
        $tier = VendorPartnershipTier::findOrFail($id);
        $data = $this->validateTier($request, $tier->id);
        $tier->update($data);

        return ApiResponse::success('Partnership tier updated.', [
            'tier' => $this->partnership->tierToArray($tier->fresh()),
        ]);
    }

    public function destroyTier(int $id): JsonResponse
    {
        $tier = VendorPartnershipTier::findOrFail($id);
        $tier->update(['is_active' => false]);

        return ApiResponse::success('Partnership tier deactivated.');
    }

    public function indexApplications(Request $request): JsonResponse
    {
        $items = VendorPartnershipApplication::query()
            ->with(['tier', 'vendor.profile', 'vendor.user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ApiResponse::success('Partnership applications.', [
            'items' => collect($items->items())->map(function ($application) {
                $payload = $this->partnership->applicationToArray($application);
                $payload['vendor'] = [
                    'id' => $application->vendor_id,
                    'business_name' => $application->vendor?->profile?->business_name,
                    'email' => $application->vendor?->profile?->email ?? $application->vendor?->user?->email,
                ];

                return $payload;
            })->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function showApplication(int $id): JsonResponse
    {
        $application = VendorPartnershipApplication::with(['tier', 'vendor.profile', 'vendor.user'])
            ->findOrFail($id);

        $payload = $this->partnership->applicationToArray($application);
        $payload['vendor'] = [
            'id' => $application->vendor_id,
            'business_name' => $application->vendor?->profile?->business_name,
            'email' => $application->vendor?->profile?->email ?? $application->vendor?->user?->email,
            'phone' => $application->vendor?->profile?->phone,
        ];

        return ApiResponse::success('Partnership application.', ['application' => $payload]);
    }

    public function approveApplication(Request $request, int $id): JsonResponse
    {
        $application = VendorPartnershipApplication::with('tier')->findOrFail($id);
        $data = $request->validate(['admin_notes' => 'nullable|string|max:2000']);

        try {
            $partnership = $this->partnership->approveApplication(
                $application,
                $request->user(),
                $data['admin_notes'] ?? null
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?? 'Could not approve application.',
                422,
                $e->errors()
            );
        }

        return ApiResponse::success('Application approved.', [
            'partnership' => $this->partnership->partnershipToArray($partnership),
        ]);
    }

    public function rejectApplication(Request $request, int $id): JsonResponse
    {
        $application = VendorPartnershipApplication::findOrFail($id);
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        try {
            $application = $this->partnership->rejectApplication(
                $application,
                $request->user(),
                $data['reason']
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?? 'Could not reject application.',
                422,
                $e->errors()
            );
        }

        return ApiResponse::success('Application rejected.', [
            'application' => $this->partnership->applicationToArray($application),
        ]);
    }

    public function showVendorPartnership(int $vendorId): JsonResponse
    {
        $vendor = Vendor::with('profile')->findOrFail($vendorId);

        return ApiResponse::success('Vendor partnership.', $this->partnership->vendorDashboard($vendor));
    }

    public function assignVendorPartnership(Request $request, int $vendorId): JsonResponse
    {
        $vendor = Vendor::findOrFail($vendorId);
        $data = $request->validate([
            'tier_id' => 'required|integer|exists:vendor_partnership_tiers,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'payment_method' => 'nullable|string|in:credit_card,bank_transfer,digital_wallet',
            'estimated_products' => 'nullable|integer|min:1',
            'business_description' => 'nullable|string|max:5000',
            'contact_phone' => 'nullable|string|max:30',
        ]);

        $tier = VendorPartnershipTier::findOrFail($data['tier_id']);
        $partnership = $this->partnership->assignPartnership($vendor, $tier, $request->user(), $data);

        return ApiResponse::success('Partnership assigned.', [
            'partnership' => $this->partnership->partnershipToArray($partnership),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTier(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = 'required|string|max:50|unique:vendor_partnership_tiers,slug';
        if ($ignoreId !== null) {
            $slugRule .= ','.$ignoreId;
        }

        return $request->validate([
            'slug' => $slugRule,
            'name' => 'required|string|max:100',
            'badge_color' => 'nullable|string|max:30',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'duration_months' => 'required|integer|min:1|max:36',
            'required_products_min' => 'required|integer|min:0',
            'required_products_max' => 'nullable|integer|gte:required_products_min',
            'max_product_listings' => 'nullable|integer|min:1',
            'max_partner_product_images' => 'required|integer|min:1|max:50',
            'marketing_exposure' => 'required|string|in:low,medium,high',
            'social_media_posts_per_month' => 'required|integer|min:0|max:100',
            'app_banners' => 'required|integer|min:0|max:20',
            'home_banner_size' => 'required|string|in:none,small,medium,full',
            'benefits' => 'nullable|array',
            'benefits.*' => 'string|max:500',
            'features' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
