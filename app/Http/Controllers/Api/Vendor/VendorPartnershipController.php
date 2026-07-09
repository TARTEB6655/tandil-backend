<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Exceptions\PartnershipLimitExceededException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorPartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorPartnershipController extends Controller
{
    public function __construct(
        private readonly VendorPartnershipService $partnership
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');

        return ApiResponse::success('Partnership details.', $this->partnership->vendorDashboard($vendor));
    }

    public function tiers(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $current = $this->partnership->currentPartnership($vendor);
        $currentSort = $current?->tier?->sort_order ?? 0;

        $tiers = $this->partnership->availableTiers()
            ->map(function ($tier) use ($current, $currentSort) {
                $payload = $this->partnership->tierToArray($tier);
                $payload['is_current'] = $current?->tier_id === $tier->id;
                $payload['can_upgrade'] = $tier->sort_order > $currentSort;
                $payload['action_label'] = $current === null
                    ? 'Apply for '.$tier->name
                    : 'Upgrade to '.$tier->name;

                return $payload;
            })
            ->values()
            ->all();

        return ApiResponse::success('Partnership tiers.', ['tiers' => $tiers]);
    }

    public function applications(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $items = $vendor->partnershipApplications()
            ->with('tier')
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 50));

        return ApiResponse::success('Partnership applications.', [
            'items' => collect($items->items())
                ->map(fn ($app) => $this->partnership->applicationToArray($app))
                ->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function storeApplication(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $data = $request->validate([
            'tier_id' => 'required|integer|exists:vendor_partnership_tiers,id',
            'estimated_products' => 'required|integer|min:1',
            'business_description' => 'required|string|max:5000',
            'contact_phone' => 'required|string|max:30',
            'payment_method' => 'required|string|in:credit_card,bank_transfer,digital_wallet',
        ]);

        try {
            $application = $this->partnership->submitApplication($vendor, $data);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                422,
                $e->errors()
            );
        }

        return ApiResponse::success(
            'Partnership application submitted successfully.',
            ['application' => $this->partnership->applicationToArray($application)],
            201
        );
    }

    public function limits(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $tier = $this->partnership->activeTier($vendor);
        $usage = $this->partnership->usage($vendor);

        if ($tier === null) {
            return ApiResponse::success('No active partnership.', [
                'has_partnership' => false,
                'usage' => $usage,
                'product_usage' => $usage['product_usage'],
                'limits' => null,
            ]);
        }

        return ApiResponse::success('Partnership limits.', [
            'has_partnership' => true,
            'tier' => $this->partnership->tierToArray($tier),
            'usage' => $usage,
            'product_usage' => $usage['product_usage'],
            'limits' => $this->partnership->limitsForTier($tier, $usage),
        ]);
    }

    public function checkFeature(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $data = $request->validate([
            'feature' => 'required|string|max:100',
        ]);

        $labels = [
            'in_app_banner' => 'In-app banner',
            'home_banner' => 'Home page banner',
            'social_media_post' => 'Social media posts',
            'exclusive_offers' => 'Exclusive offers listing',
            'discount_code' => 'Discount codes',
            'video_content' => 'Video content',
            'notification_logo' => 'Notification logo placement',
        ];

        $feature = $data['feature'];
        $label = $labels[$feature] ?? ucfirst(str_replace('_', ' ', $feature));

        try {
            $this->partnership->assertFeature($vendor, $feature, $label);
        } catch (PartnershipLimitExceededException $e) {
            return ApiResponse::error($e->getMessage(), 403, $e->toErrorPayload());
        }

        return ApiResponse::success('Feature is available in your plan.', [
            'feature' => $feature,
            'allowed' => true,
        ]);
    }
}
