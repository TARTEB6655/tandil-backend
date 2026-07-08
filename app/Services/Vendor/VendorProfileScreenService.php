<?php

namespace App\Services\Vendor;

use App\Models\Vendor;

class VendorProfileScreenService
{
    public const SECTION_EDIT_PROFILE = 'edit_profile';

    public const SECTION_BUSINESS_INFORMATION = 'business_information';

    public const SECTION_LOCATION_ADDRESS = 'location_address';

    public const SECTION_PAYMENT_METHODS = 'payment_methods';

    /** @var list<string> */
    public const ALL_SECTIONS = [
        self::SECTION_EDIT_PROFILE,
        self::SECTION_BUSINESS_INFORMATION,
        self::SECTION_LOCATION_ADDRESS,
        self::SECTION_PAYMENT_METHODS,
    ];

    public function __construct(
        private readonly VendorDashboardService $dashboard
    ) {}

    /**
     * Mobile Vendor Portal profile payload.
     *
     * Default (no sections): Profile tab only — header, summary, stats, account_settings.
     * With sections: include edit-form blocks for Account Settings sub-screens.
     *
     * @param  list<string>  $sections
     * @return array<string, mixed>|null
     */
    public function build(?Vendor $vendor, array $sections = []): ?array
    {
        if ($vendor === null) {
            return null;
        }

        $vendor->loadMissing('profile', 'user');
        $profile = $vendor->profile;
        $user = $vendor->user;
        $stats = $this->dashboard->stats($vendor);

        $delivered = (int) ($stats['completed_orders'] ?? 0);
        $products = (int) ($stats['total_products'] ?? 0);
        $partnership = $this->partnershipBadge($vendor, $delivered);

        $memberSince = $vendor->approved_at ?? $vendor->created_at;
        $locationParts = array_values(array_filter([
            $profile?->city,
            $profile?->emirate,
            ($profile?->city || $profile?->emirate) ? 'UAE' : null,
        ]));

        $payload = [
            'header' => [
                'name' => $profile?->owner_name ?: $user?->name,
                'subtitle' => $this->headerSubtitle($profile?->business_name, $locationParts),
            ],
            'summary' => [
                'profile_image_url' => $vendor->logo_url ?: $user?->profile_picture_url,
                'professional_category' => $profile?->vendor_type_label,
                'partnership_badge' => $partnership,
                'member_since' => $memberSince ? $memberSince->format('F Y') : null,
                'member_since_iso' => $memberSince?->toIso8601String(),
            ],
            'stats' => [
                'products' => $products,
                'delivered' => $delivered,
                'rating' => 0,
                'reviews' => 0,
                'rating_available' => false,
            ],
            'account_settings' => [
                ['id' => self::SECTION_EDIT_PROFILE, 'title' => 'Edit Profile', 'icon' => 'person'],
                ['id' => self::SECTION_BUSINESS_INFORMATION, 'title' => 'Business Information', 'icon' => 'business'],
                ['id' => self::SECTION_LOCATION_ADDRESS, 'title' => 'Location & Address', 'icon' => 'location'],
                ['id' => self::SECTION_PAYMENT_METHODS, 'title' => 'Payment Methods', 'icon' => 'payment'],
            ],
        ];

        foreach ($this->normalizeSections($sections) as $section) {
            $payload[$section] = $this->sectionPayload($section, $vendor, $profile, $user, $locationParts);
        }

        return $payload;
    }

    /**
     * After save — tab fields plus all edit sections (no application / logs).
     *
     * @return array<string, mixed>|null
     */
    public function buildAfterUpdate(?Vendor $vendor): ?array
    {
        return $this->build($vendor, self::ALL_SECTIONS);
    }

    /**
     * @param  list<string>  $sections
     * @return list<string>
     */
    public function normalizeSections(array $sections): array
    {
        $normalized = [];

        foreach ($sections as $section) {
            $key = strtolower(trim($section));
            if ($key === 'all') {
                return self::ALL_SECTIONS;
            }
            if (in_array($key, self::ALL_SECTIONS, true)) {
                $normalized[] = $key;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<string>  $locationParts
     * @return array<string, mixed>
     */
    private function sectionPayload(
        string $section,
        Vendor $vendor,
        $profile,
        $user,
        array $locationParts
    ): array {
        return match ($section) {
            self::SECTION_EDIT_PROFILE => [
                'owner_name' => $profile?->owner_name,
                'email' => $profile?->email ?: $user?->email,
                'phone' => $profile?->phone ?: $user?->phone,
                'description' => $profile?->description,
                'logo_url' => $vendor->logo_url,
                'profile_picture_url' => $user?->profile_picture_url,
            ],
            self::SECTION_BUSINESS_INFORMATION => [
                'business_name' => $profile?->business_name,
                'vendor_type' => $profile?->vendor_type,
                'vendor_type_label' => $profile?->vendor_type_label,
                'trade_license_number' => $profile?->trade_license_number,
                'tax_vat_number' => $profile?->tax_vat_number,
                'years_in_business' => $profile?->years_in_business,
                'operating_hours' => $profile?->operating_hours,
                'minimum_order_amount' => $profile?->minimum_order_amount !== null
                    ? (float) $profile->minimum_order_amount
                    : null,
            ],
            self::SECTION_LOCATION_ADDRESS => [
                'emirate' => $profile?->emirate,
                'city' => $profile?->city,
                'address' => $profile?->address,
                'google_maps_location' => $profile?->google_maps_location,
                'delivery_radius' => $profile?->delivery_radius !== null
                    ? (float) $profile->delivery_radius
                    : null,
                'location_display' => implode(', ', $locationParts) ?: null,
            ],
            self::SECTION_PAYMENT_METHODS => [
                'bank_name' => $profile?->bank_name,
                'iban' => $profile?->iban,
                'account_holder_name' => $profile?->account_holder_name,
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $locationParts
     */
    private function headerSubtitle(?string $businessName, array $locationParts): ?string
    {
        $location = implode(', ', $locationParts);
        if ($businessName && $location !== '') {
            return $businessName.' · '.$location;
        }

        return $businessName ?: ($location !== '' ? $location : null);
    }

    /**
     * @return array{label: string, tier: string}
     */
    private function partnershipBadge(Vendor $vendor, int $deliveredOrders): array
    {
        if (! $vendor->isApproved()) {
            return ['label' => 'New Vendor', 'tier' => 'new'];
        }

        if ($deliveredOrders >= 500) {
            return ['label' => 'Gold Partner', 'tier' => 'gold'];
        }

        if ($deliveredOrders >= 50) {
            return ['label' => 'Silver Partner', 'tier' => 'silver'];
        }

        return ['label' => 'Bronze Partner', 'tier' => 'bronze'];
    }
}
