<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorPartnershipApplicationStatus;
use App\Enums\VendorPartnershipStatus;
use App\Exceptions\PartnershipLimitExceededException;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorPartnership;
use App\Models\VendorPartnershipApplication;
use App\Models\VendorPartnershipTier;
use App\Models\VendorProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorPartnershipService
{
    public const DEFAULT_FREE_PRODUCT_LIMIT = 5;

    public const DEFAULT_FREE_PRODUCT_IMAGES = 1;

    public function currentPartnership(Vendor $vendor): ?VendorPartnership
    {
        return VendorPartnership::query()
            ->with('tier')
            ->where('vendor_id', $vendor->id)
            ->where('status', VendorPartnershipStatus::Active->value)
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    public function activeTier(Vendor $vendor): ?VendorPartnershipTier
    {
        return $this->currentPartnership($vendor)?->tier;
    }

    /**
     * @return Collection<int, VendorPartnershipTier>
     */
    public function availableTiers(): Collection
    {
        return VendorPartnershipTier::query()
            ->active()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function vendorDashboard(Vendor $vendor): array
    {
        $partnership = $this->currentPartnership($vendor);
        $tier = $partnership?->tier;
        $usage = $this->usage($vendor);

        return [
            'has_partnership' => $partnership !== null,
            'partnership' => $partnership ? $this->partnershipToArray($partnership) : null,
            'tier' => $tier ? $this->tierToArray($tier) : null,
            'usage' => $usage,
            'product_usage' => $usage['product_usage'],
            'stats_cards' => $this->buildStatsCards($usage),
            'partnership_details' => $partnership && $tier
                ? $this->partnershipDetailsCard($partnership, $tier)
                : null,
            'limits' => $tier
                ? $this->limitsForTier($tier, $usage)
                : $this->defaultFreeLimits($usage),
            'pending_application' => $this->pendingApplication($vendor)
                ? $this->applicationToArray($this->pendingApplication($vendor))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(Vendor $vendor): array
    {
        $productsUsed = VendorProduct::where('vendor_id', $vendor->id)->count();
        $deliveredOrders = VendorOrderMapping::where('vendor_id', $vendor->id)
            ->where('status', VendorOrderStatus::Delivered->value)
            ->count();

        $partnership = $this->currentPartnership($vendor);
        $resolved = $this->resolveProductLimit($vendor, $partnership);
        $limit = $resolved['limit'];
        $unlimited = $resolved['unlimited'];
        $remaining = $this->remainingProductSlots($limit, $productsUsed, $unlimited);
        $canAddMore = $unlimited || ($remaining !== null && $remaining > 0);

        $productUsage = [
            'limit' => $limit,
            'used' => $productsUsed,
            'remaining' => $remaining,
            'unlimited' => $unlimited,
            'plan_type' => $resolved['plan_type'],
            'tier_slug' => $resolved['tier_slug'],
            'tier_name' => $resolved['tier_name'],
            'can_add_more' => $canAddMore,
            'upgrade_required' => ! $canAddMore,
            'message' => $this->productUsageMessage($partnership, $productsUsed, $limit, $remaining, $unlimited, $resolved),
        ];

        return [
            'products_used' => $productsUsed,
            'product_limit' => $limit,
            'products_remaining' => $remaining,
            'delivered_orders' => $deliveredOrders,
            'days_left' => $partnership?->daysRemaining() ?? 0,
            'product_usage' => $productUsage,
            // Backward-compatible aliases
            'total_products' => $productsUsed,
            'remaining_product_slots' => $remaining,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsForTier(VendorPartnershipTier $tier, array $usage): array
    {
        $productUsage = $usage['product_usage'];

        return [
            'max_product_listings' => $tier->max_product_listings,
            'max_partner_product_images' => $tier->max_partner_product_images,
            'marketing_exposure' => $tier->marketing_exposure,
            'social_media_posts_per_month' => $tier->social_media_posts_per_month,
            'app_banners' => $tier->app_banners,
            'home_banner_size' => $tier->home_banner_size,
            'products_used' => $productUsage['used'],
            'products_remaining' => $productUsage['remaining'],
            'product_limit' => $productUsage['limit'],
            'unlimited_products' => $productUsage['unlimited'],
            'can_add_more_products' => $productUsage['can_add_more'],
        ];
    }

    public function pendingApplication(Vendor $vendor): ?VendorPartnershipApplication
    {
        return VendorPartnershipApplication::query()
            ->with('tier')
            ->where('vendor_id', $vendor->id)
            ->where('status', VendorPartnershipApplicationStatus::Pending->value)
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitApplication(Vendor $vendor, array $data): VendorPartnershipApplication
    {
        if ($this->pendingApplication($vendor) !== null) {
            throw ValidationException::withMessages([
                'application' => 'You already have a pending partnership application.',
            ]);
        }

        $tier = VendorPartnershipTier::active()->findOrFail($data['tier_id']);

        $this->validateEstimatedProducts($tier, (int) $data['estimated_products']);

        $current = $this->currentPartnership($vendor);
        $type = 'new';
        if ($current !== null) {
            if ($current->tier_id === $tier->id) {
                throw ValidationException::withMessages([
                    'tier_id' => 'You are already on this partnership tier.',
                ]);
            }
            if ($tier->sort_order <= ($current->tier?->sort_order ?? 0)) {
                throw ValidationException::withMessages([
                    'tier_id' => 'Please select a higher tier to upgrade your partnership.',
                ]);
            }
            $type = 'upgrade';
        }

        return VendorPartnershipApplication::create([
            'vendor_id' => $vendor->id,
            'tier_id' => $tier->id,
            'type' => $type,
            'estimated_products' => (int) $data['estimated_products'],
            'business_description' => $data['business_description'],
            'contact_phone' => $data['contact_phone'],
            'payment_method' => $data['payment_method'],
            'status' => VendorPartnershipApplicationStatus::Pending->value,
        ])->load('tier');
    }

    public function approveApplication(VendorPartnershipApplication $application, User $admin, ?string $notes = null): VendorPartnership
    {
        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'application' => 'Only pending applications can be approved.',
            ]);
        }

        return DB::transaction(function () use ($application, $admin, $notes) {
            $application->update([
                'status' => VendorPartnershipApplicationStatus::Approved->value,
                'admin_notes' => $notes,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            VendorPartnership::query()
                ->where('vendor_id', $application->vendor_id)
                ->where('status', VendorPartnershipStatus::Active->value)
                ->update(['status' => VendorPartnershipStatus::Cancelled->value]);

            $tier = $application->tier;
            $startsAt = now();
            $endsAt = $startsAt->copy()->addMonths($tier->duration_months);

            return VendorPartnership::create([
                'vendor_id' => $application->vendor_id,
                'tier_id' => $application->tier_id,
                'status' => VendorPartnershipStatus::Active->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'next_payment_at' => $endsAt,
                'payment_method' => $application->payment_method,
                'estimated_products' => $application->estimated_products,
                'business_description' => $application->business_description,
                'contact_phone' => $application->contact_phone,
                'assigned_by' => $admin->id,
                'application_id' => $application->id,
            ])->load('tier');
        });
    }

    public function rejectApplication(
        VendorPartnershipApplication $application,
        User $admin,
        string $reason
    ): VendorPartnershipApplication {
        if (! $application->isPending()) {
            throw ValidationException::withMessages([
                'application' => 'Only pending applications can be rejected.',
            ]);
        }

        $application->update([
            'status' => VendorPartnershipApplicationStatus::Rejected->value,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        return $application->fresh(['tier', 'vendor.profile']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assignPartnership(Vendor $vendor, VendorPartnershipTier $tier, User $admin, array $data = []): VendorPartnership
    {
        return DB::transaction(function () use ($vendor, $tier, $admin, $data) {
            VendorPartnership::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', VendorPartnershipStatus::Active->value)
                ->update(['status' => VendorPartnershipStatus::Cancelled->value]);

            $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
            $endsAt = isset($data['ends_at'])
                ? Carbon::parse($data['ends_at'])
                : $startsAt->copy()->addMonths($tier->duration_months);

            return VendorPartnership::create([
                'vendor_id' => $vendor->id,
                'tier_id' => $tier->id,
                'status' => VendorPartnershipStatus::Active->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'next_payment_at' => $endsAt,
                'payment_method' => $data['payment_method'] ?? null,
                'estimated_products' => $data['estimated_products'] ?? null,
                'business_description' => $data['business_description'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'assigned_by' => $admin->id,
            ])->load('tier');
        });
    }

    public function assertCanCreateProduct(Vendor $vendor): void
    {
        $partnership = $this->currentPartnership($vendor);
        $resolved = $this->resolveProductLimit($vendor, $partnership);

        if ($resolved['unlimited']) {
            return;
        }

        $current = VendorProduct::where('vendor_id', $vendor->id)->count();
        $max = (int) $resolved['limit'];

        if ($current >= $max) {
            $tierName = $resolved['tier_name'];
            $message = $resolved['plan_type'] === 'free_default'
                ? "You have reached the free limit of {$max} products. Apply for a partnership plan to add more."
                : "Your {$tierName} plan allows up to {$max} products. Upgrade your partnership to add more.";

            throw new PartnershipLimitExceededException(
                $message,
                'max_product_listings',
                $current,
                $max,
                $resolved['tier_slug'],
                true
            );
        }
    }

    public function assertCanAddProductImages(Vendor $vendor, int $imageCount): void
    {
        $partnership = $this->currentPartnership($vendor);
        $tier = $partnership?->tier;
        $max = $tier?->max_partner_product_images ?? self::DEFAULT_FREE_PRODUCT_IMAGES;
        $tierName = $tier?->name ?? 'Free';
        $tierSlug = $tier?->slug ?? 'free';

        if ($imageCount > $max) {
            $message = $tier === null
                ? "Without a partnership plan you can upload up to {$max} product image(s). Apply for a partnership to add more."
                : "Your {$tierName} plan allows up to {$max} product images per listing. Upgrade your partnership for more.";

            throw new PartnershipLimitExceededException(
                $message,
                'max_partner_product_images',
                $imageCount,
                $max,
                $tierSlug,
                true
            );
        }
    }

    public function assertFeature(Vendor $vendor, string $featureKey, string $featureLabel): void
    {
        $tier = $this->activeTier($vendor);
        if ($tier === null || ! $tier->featureEnabled($featureKey)) {
            $tierName = $tier?->name ?? 'current';
            throw new PartnershipLimitExceededException(
                "{$featureLabel} is not included in your {$tierName} partnership plan. Please upgrade to unlock this feature.",
                'feature_'.$featureKey,
                0,
                null,
                $tier?->slug,
                true
            );
        }
    }

    private function validateEstimatedProducts(VendorPartnershipTier $tier, int $estimated): void
    {
        if ($estimated < $tier->required_products_min) {
            throw ValidationException::withMessages([
                'estimated_products' => "This tier requires at least {$tier->required_products_min} products.",
            ]);
        }

        if ($tier->required_products_max !== null && $estimated > $tier->required_products_max) {
            throw ValidationException::withMessages([
                'estimated_products' => "This tier accepts up to {$tier->required_products_max} estimated products.",
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function tierToArray(VendorPartnershipTier $tier): array
    {
        return [
            'id' => $tier->id,
            'slug' => $tier->slug,
            'name' => $tier->name,
            'badge_color' => $tier->badge_color,
            'price' => (float) $tier->price,
            'currency' => $tier->currency,
            'duration_months' => $tier->duration_months,
            'duration_label' => $this->durationLabel($tier->duration_months),
            'required_products_min' => $tier->required_products_min,
            'required_products_max' => $tier->required_products_max,
            'required_products_label' => $this->requiredProductsLabel($tier),
            'max_product_listings' => $tier->max_product_listings,
            'max_partner_product_images' => $tier->max_partner_product_images,
            'marketing_exposure' => $tier->marketing_exposure,
            'social_media_posts_per_month' => $tier->social_media_posts_per_month,
            'app_banners' => $tier->app_banners,
            'home_banner_size' => $tier->home_banner_size,
            'benefits' => $tier->benefits ?? [],
            'features' => $tier->features ?? [],
            'is_active' => $tier->is_active,
            'sort_order' => $tier->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function partnershipToArray(VendorPartnership $partnership): array
    {
        $partnership->loadMissing('tier');

        return [
            'id' => $partnership->id,
            'status' => $partnership->status,
            'status_label' => VendorPartnershipStatus::tryFrom($partnership->status)?->label() ?? $partnership->status,
            'starts_at' => $partnership->starts_at?->toIso8601String(),
            'ends_at' => $partnership->ends_at?->toIso8601String(),
            'next_payment_at' => $partnership->next_payment_at?->toIso8601String(),
            'next_payment_label' => $partnership->next_payment_at?->format('j/n/Y'),
            'days_left' => $partnership->daysRemaining(),
            'payment_method' => $partnership->payment_method,
            'estimated_products' => $partnership->estimated_products,
            'tier' => $partnership->tier ? $this->tierToArray($partnership->tier) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationToArray(VendorPartnershipApplication $application): array
    {
        $application->loadMissing('tier');

        return [
            'id' => $application->id,
            'type' => $application->type,
            'status' => $application->status,
            'status_label' => VendorPartnershipApplicationStatus::tryFrom($application->status)?->label() ?? $application->status,
            'estimated_products' => $application->estimated_products,
            'business_description' => $application->business_description,
            'contact_phone' => $application->contact_phone,
            'payment_method' => $application->payment_method,
            'rejection_reason' => $application->rejection_reason,
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
            'created_at' => $application->created_at?->toIso8601String(),
            'tier' => $application->tier ? $this->tierToArray($application->tier) : null,
        ];
    }

    private function durationLabel(int $months): string
    {
        if ($months === 12) {
            return '12 months (1 year)';
        }
        if ($months === 1) {
            return '1 month';
        }

        return "{$months} months";
    }

    private function requiredProductsLabel(VendorPartnershipTier $tier): string
    {
        if ($tier->required_products_max === null) {
            return $tier->required_products_min.'+ free products';
        }

        return "{$tier->required_products_min}-{$tier->required_products_max} free products";
    }

    private function remainingProductSlots(?int $limit, int $used, bool $unlimited): ?int
    {
        if ($unlimited) {
            return null;
        }

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $used);
    }

    private function productUsageMessage(
        ?VendorPartnership $partnership,
        int $used,
        ?int $limit,
        ?int $remaining,
        bool $unlimited,
        array $resolved
    ): string {
        if ($partnership === null) {
            if ($remaining !== null && $remaining <= 0) {
                return 'You have used all '.self::DEFAULT_FREE_PRODUCT_LIMIT.' free product slots. Apply for a partnership plan to add more products.';
            }

            return "You have used {$used} of ".self::DEFAULT_FREE_PRODUCT_LIMIT.' free product slots. '.($remaining ?? 0).' remaining. Apply for a partnership to unlock more.';
        }

        if ($unlimited) {
            return "You have added {$used} products. Your plan allows unlimited product listings.";
        }

        if ($remaining !== null && $remaining <= 0) {
            return "You have used all {$limit} product slots in your {$resolved['tier_name']} plan. Upgrade to add more products.";
        }

        return "You have used {$used} of {$limit} product slots. {$remaining} remaining in your {$resolved['tier_name']} plan.";
    }

    /**
     * @return array{limit: ?int, unlimited: bool, tier_slug: string, plan_type: string, tier_name: string}
     */
    private function resolveProductLimit(Vendor $vendor, ?VendorPartnership $partnership): array
    {
        $tier = $partnership?->tier;

        if ($tier === null) {
            return [
                'limit' => self::DEFAULT_FREE_PRODUCT_LIMIT,
                'unlimited' => false,
                'tier_slug' => 'free',
                'plan_type' => 'free_default',
                'tier_name' => 'Free',
            ];
        }

        return [
            'limit' => $tier->max_product_listings,
            'unlimited' => $tier->hasUnlimitedProducts(),
            'tier_slug' => $tier->slug,
            'plan_type' => 'paid',
            'tier_name' => $tier->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array<string, mixed>
     */
    public function defaultFreeLimits(array $usage): array
    {
        $productUsage = $usage['product_usage'];

        return [
            'max_product_listings' => self::DEFAULT_FREE_PRODUCT_LIMIT,
            'max_partner_product_images' => self::DEFAULT_FREE_PRODUCT_IMAGES,
            'marketing_exposure' => 'none',
            'social_media_posts_per_month' => 0,
            'app_banners' => 0,
            'home_banner_size' => 'none',
            'products_used' => $productUsage['used'],
            'products_remaining' => $productUsage['remaining'],
            'product_limit' => self::DEFAULT_FREE_PRODUCT_LIMIT,
            'unlimited_products' => false,
            'can_add_more_products' => $productUsage['can_add_more'],
            'plan_type' => 'free_default',
        ];
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array<int, array<string, mixed>>
     */
    private function buildStatsCards(array $usage): array
    {
        $productUsage = $usage['product_usage'];
        $limit = $productUsage['limit'];
        $remaining = $productUsage['remaining'];

        return [
            [
                'key' => 'product_limit',
                'label' => 'Total Products',
                'description' => 'Maximum products allowed in your plan',
                'value' => $productUsage['unlimited'] ? 'Unlimited' : ($limit ?? 0),
                'numeric_value' => $limit,
            ],
            [
                'key' => 'products_used',
                'label' => 'Used',
                'description' => 'Products you have already added',
                'value' => $productUsage['used'],
                'numeric_value' => $productUsage['used'],
            ],
            [
                'key' => 'products_remaining',
                'label' => 'Remaining',
                'description' => 'Product slots still available in your plan',
                'value' => $productUsage['unlimited'] ? 'Unlimited' : ($remaining ?? 0),
                'numeric_value' => $remaining,
            ],
            [
                'key' => 'days_left',
                'label' => 'Days Left',
                'description' => 'Days until your partnership renews',
                'value' => $usage['days_left'],
                'numeric_value' => $usage['days_left'],
            ],
            [
                'key' => 'delivered_orders',
                'label' => 'Delivered',
                'description' => 'Orders delivered to customers',
                'value' => $usage['delivered_orders'],
                'numeric_value' => $usage['delivered_orders'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partnershipDetailsCard(VendorPartnership $partnership, VendorPartnershipTier $tier): array
    {
        return [
            'next_payment' => $partnership->next_payment_at?->format('j/n/Y'),
            'marketing_exposure' => ucfirst($tier->marketing_exposure),
            'social_media_posts' => $tier->social_media_posts_per_month.'/month',
            'app_banners' => $tier->app_banners.' active',
        ];
    }
}
