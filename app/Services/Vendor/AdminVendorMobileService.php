<?php

namespace App\Services\Vendor;

use App\Enums\VendorProductApprovalStatus;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Services\ProfilePictureUploadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AdminVendorMobileService
{
    public function __construct(
        private readonly AdminMarketplaceAnalyticsService $analytics,
        private readonly AdminVendorMetricsService $metrics,
        private readonly AdminVendorProductListService $productList
    ) {}

    /**
     * Admin mobile — Vendor Management screen (summary cards + vendor list).
     *
     * @return array<string, mixed>
     */
    public function managementIndex(Request $request): array
    {
        $overview = $this->analytics->overview();

        $paginator = $this->vendorQuery($request)->paginate(
            min($request->integer('per_page', 15), 100)
        );

        $vendorIds = collect($paginator->items())->pluck('id')->all();
        $metricsMap = $this->metrics->mapForVendorIds($vendorIds);

        return [
            'summary' => [
                'vendors' => (int) ($overview['vendors']['total'] ?? 0),
                'products' => (int) ($overview['products']['total'] ?? 0),
                'revenue' => (float) ($overview['revenue']['gross'] ?? 0),
                'revenue_formatted' => $this->formatAed((float) ($overview['revenue']['gross'] ?? 0)),
            ],
            'items' => collect($paginator->items())
                ->map(fn (Vendor $vendor) => $this->formatManagementListItem(
                    $vendor,
                    $metricsMap[$vendor->id] ?? null
                ))
                ->values()
                ->all(),
            'pagination' => $this->pagination($paginator),
        ];
    }

    /**
     * Admin mobile — single vendor detail (header, KPIs, products with toggle metadata).
     *
     * @return array<string, mixed>
     */
    public function managementDetail(Vendor $vendor, Request $request): array
    {
        $vendor->loadMissing(['profile', 'user']);
        $profile = $vendor->profile;
        $metrics = $this->metrics->forVendor($vendor);
        $productStats = $this->productList->stats($vendor);

        $paginator = $this->productList->paginate($vendor, $request);

        return [
            'vendor' => array_merge([
                'id' => $vendor->id,
                'business_name' => $profile?->business_name,
                'owner_name' => $profile?->owner_name,
                'email' => $profile?->email ?? $vendor->user?->email,
                'phone' => $profile?->phone ?? $vendor->user?->phone,
                'status' => $vendor->status,
                'status_label' => $vendor->statusEnum()->label(),
            ], $this->vendorImageFields($vendor)),
            'summary' => [
                'total_revenue' => (float) ($metrics['revenue'] ?? 0),
                'total_revenue_formatted' => $this->formatAed((float) ($metrics['revenue'] ?? 0)),
                'total_products' => (int) ($productStats['total'] ?? 0),
                'enabled_products' => (int) ($productStats['active'] ?? 0),
                'disabled_products' => (int) ($productStats['disabled'] ?? 0),
                'pending_products' => (int) ($productStats['pending'] ?? 0),
            ],
            'products' => [
                'count' => $paginator->total(),
                'items' => collect($paginator->items())
                    ->map(fn (VendorProduct $vp) => $this->formatProductItem($vendor, $vp))
                    ->values()
                    ->all(),
                'pagination' => $this->pagination($paginator),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatProductItem(Vendor $vendor, VendorProduct $vp): array
    {
        $vp->loadMissing(['product.primaryImage', 'product.images', 'inventory', 'currentPrice']);
        $product = $vp->product;
        $price = (float) ($vp->currentPrice?->price ?? $product?->price ?? 0);
        $stock = (int) ($vp->inventory?->quantity ?? $product?->stock ?? 0);
        $isEnabled = $this->isProductEnabledForToggle($vp);

        return [
            'id' => $vp->id,
            'vendor_product_id' => $vp->id,
            'product_id' => $vp->product_id,
            'name' => $product?->name,
            'price' => round($price, 2),
            'price_formatted' => $this->formatAed($price),
            'stock' => $stock,
            'is_enabled' => $isEnabled,
            'status' => $isEnabled ? 'enabled' : 'disabled',
            'status_label' => $isEnabled ? 'Enabled' : 'Disabled',
            'display_status' => $vp->displayStatusKey(),
            'display_status_label' => $vp->displayStatusLabel(),
            'approval_status' => $vp->approval_status,
            'disabled_by_admin' => (bool) $vp->disabled_by_admin,
            'image_url' => $product?->image_url,
            'can_toggle' => $vp->approval_status === VendorProductApprovalStatus::Approved->value,
            'actions' => [
                'toggle' => [
                    'method' => 'POST',
                    'endpoint' => "/api/admin/vendors/{$vendor->id}/products/{$vp->id}/toggle",
                ],
            ],
        ];
    }

    /**
     * @param  array<string, int|float>|null  $metrics
     * @return array<string, mixed>
     */
    private function formatManagementListItem(Vendor $vendor, ?array $metrics = null): array
    {
        $vendor->loadMissing(['profile', 'user']);
        $profile = $vendor->profile;
        $metrics ??= $this->metrics->emptyMetricsPublic();
        $revenue = (float) ($metrics['revenue'] ?? 0);

        return array_merge([
            'id' => $vendor->id,
            'vendor_id' => $vendor->id,
            'business_name' => $profile?->business_name,
            'owner_name' => $profile?->owner_name,
            'email' => $profile?->email ?? $vendor->user?->email,
            'phone' => $profile?->phone ?? $vendor->user?->phone,
            'status' => $vendor->status,
            'status_label' => $vendor->statusEnum()->label(),
            'products_count' => (int) ($metrics['total_products'] ?? 0),
            'active_count' => (int) ($metrics['active_products'] ?? 0),
            'revenue' => $revenue,
            'revenue_formatted' => $this->formatAed($revenue),
            'metrics' => $metrics,
            'detail' => [
                'method' => 'GET',
                'endpoint' => "/api/admin/vendors/{$vendor->id}/management",
            ],
        ], $this->vendorImageFields($vendor));
    }

    /**
     * @return array{logo_url: ?string, profile_picture_url: ?string, profile_url: ?string}
     */
    private function vendorImageFields(Vendor $vendor): array
    {
        $vendor->loadMissing(['profile', 'user']);
        $profile = $vendor->profile;

        $logoUrl = $profile?->logo_url ?? $vendor->logo_url;
        $profilePictureUrl = $profile?->profile_picture_url;
        $userPictureUrl = ProfilePictureUploadService::fullUrl($vendor->user?->profile_picture);

        return [
            'logo_url' => $logoUrl,
            'profile_picture_url' => $profilePictureUrl,
            'profile_url' => $logoUrl ?? $profilePictureUrl ?? $userPictureUrl,
        ];
    }

    private function vendorQuery(Request $request)
    {
        $sort = $request->query('sort', 'newest');

        return Vendor::with(['profile', 'user'])
            ->when($request->query('status'), function ($query, $status) {
                if (str_contains((string) $status, ',')) {
                    $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $status))));

                    return $query->whereIn('status', $statuses);
                }

                return $query->where('status', $status);
            })
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('profile', function ($pq) use ($search) {
                        $pq->where('business_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('owner_name', 'like', "%{$search}%");
                    })->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($sort === 'oldest', fn ($query) => $query->oldest())
            ->when($sort === 'business', fn ($query) => $query
                ->leftJoin('vendor_profiles', 'vendors.id', '=', 'vendor_profiles.vendor_id')
                ->orderBy('vendor_profiles.business_name')
                ->select('vendors.*'))
            ->when(! in_array($sort, ['oldest', 'business'], true), fn ($query) => $query->latest());
    }

    private function isProductEnabledForToggle(VendorProduct $vp): bool
    {
        return $vp->status === 'active'
            && $vp->approval_status === VendorProductApprovalStatus::Approved->value
            && ! $vp->disabled_by_admin;
    }

    private function formatAed(float $amount): string
    {
        return 'AED '.number_format($amount, 2);
    }

    /**
     * @return array<string, int>
     */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
