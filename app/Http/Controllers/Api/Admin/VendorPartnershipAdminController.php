<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorPartnershipTier;
use App\Services\Vendor\VendorPartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
