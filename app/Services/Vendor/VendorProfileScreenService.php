<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Services\ProfilePictureUploadService;

class VendorProfileScreenService
{
    public function __construct(
        private readonly VendorDashboardService $dashboard
    ) {}

    /**
     * Complete vendor profile for mobile Profile tab + Edit Profile form.
     *
     * @return array<string, mixed>|null
     */
    public function build(?Vendor $vendor): ?array
    {
        if ($vendor === null) {
            return null;
        }

        $vendor->loadMissing('profile', 'user');
        $profile = $vendor->profile;
        $user = $vendor->user;
        $stats = $this->dashboard->profileSummaryStats($vendor);

        $delivered = $stats['completed_orders'];
        $products = $stats['total_products'];
        $partnership = $this->partnershipBadge($vendor, $delivered);

        $memberSince = $vendor->approved_at ?? $vendor->created_at;
        $locationParts = array_values(array_filter([
            $profile?->city,
            $profile?->emirate,
            ($profile?->city || $profile?->emirate) ? 'UAE' : null,
        ]));

        [$opensAt, $closesAt] = $this->parseOperatingHours($profile?->operating_hours);

        return [
            'header' => [
                'name' => $profile?->owner_name ?: $user?->name,
                'subtitle' => $this->headerSubtitle($profile?->business_name, $locationParts),
            ],
            'summary' => [
                'profile_image_url' => $this->profilePictureUrl($vendor, $user),
                'profile_picture_url' => $this->profilePictureUrl($vendor, $user),
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
                ['id' => 'edit_profile', 'title' => 'Edit Profile', 'icon' => 'person'],
                ['id' => 'business_information', 'title' => 'Business Information', 'icon' => 'business'],
                ['id' => 'location_address', 'title' => 'Location & Address', 'icon' => 'location'],
                ['id' => 'payment_methods', 'title' => 'Payment Methods', 'icon' => 'payment'],
            ],
            'partnership' => [
                ['id' => 'partnership_status', 'title' => 'Partnership Status', 'icon' => 'diamond'],
                ['id' => 'performance_analytics', 'title' => 'Performance Analytics', 'icon' => 'analytics'],
                ['id' => 'partnership_documents', 'title' => 'Partnership Documents', 'icon' => 'document'],
                ['id' => 'support_team', 'title' => 'Support Team', 'icon' => 'support'],
            ],
            'edit_profile' => $this->editProfileForm($vendor, $profile, $user, $opensAt, $closesAt),
            'business_information' => $this->businessInformation($profile, $opensAt, $closesAt),
            'location_address' => $this->locationAddress($profile, $locationParts),
            'payment_methods' => $this->paymentMethods($profile),
            'read_only' => [
                'vendor_id' => $vendor->id,
                'status' => $vendor->status,
                'status_label' => $vendor->statusEnum()->label(),
                'is_approved' => $vendor->isApproved(),
                'rejection_reason' => $vendor->rejection_reason,
                'commission_rate' => $vendor->commission_rate !== null
                    ? (float) $vendor->commission_rate
                    : null,
                'registered_at' => $vendor->created_at?->toIso8601String(),
                'approved_at' => $vendor->approved_at?->toIso8601String(),
                'role' => $user?->role,
            ],
        ];
    }

    /**
     * Edit Profile screen — matches mobile UI sections (GET and POST response).
     *
     * @return array<string, mixed>
     */
    private function editProfileForm(Vendor $vendor, $profile, $user, ?string $opensAt, ?string $closesAt): array
    {
        return [
            'title' => 'Edit Profile',
            'subtitle' => 'Update contact and store operations',
            'store_branding' => [
                'profile_picture_url' => $this->profilePictureUrl($vendor, $user),
                'logo_url' => $profile?->logo_url,
                'hint' => 'Profile picture is your personal photo (upload_field: profile_picture). Business logo is separate (logo_url). Any size up to 500 MB — auto-compressed on upload.',
                'upload_field' => 'profile_picture',
                'logo_upload_field' => 'logo',
            ],
            'business_contact' => [
                'business_name' => $profile?->business_name,
                'business_name_note' => 'May require admin approval if changed after verification.',
                'contact_person' => $profile?->owner_name,
                'phone' => $profile?->phone ?: $user?->phone,
                'address' => $profile?->address,
                'city' => $profile?->city,
                'store_description' => $profile?->description,
            ],
            'business_hours' => [
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'delivery_radius_km' => $profile?->delivery_radius !== null
                    ? (float) $profile->delivery_radius
                    : null,
                'minimum_order_amount' => $profile?->minimum_order_amount !== null
                    ? (float) $profile->minimum_order_amount
                    : null,
            ],
            'bank_account' => [
                'subtitle' => 'Used for payouts. Ensure details match your trade license.',
                'bank_name' => $profile?->bank_name,
                'iban' => $profile?->iban,
                'account_holder_name' => $profile?->account_holder_name,
            ],
        ];
    }

    /**
     * Business Information screen — read-only (GET display only).
     *
     * @return array<string, mixed>
     */
    private function businessInformation($profile, ?string $opensAt, ?string $closesAt): array
    {
        return [
            'title' => 'Business Information',
            'read_only' => true,
            'business_name' => $profile?->business_name,
            'vendor_type' => $profile?->vendor_type,
            'vendor_type_label' => $profile?->vendor_type_label,
            'trade_license_number' => $profile?->trade_license_number,
            'tax_vat_number' => $profile?->tax_vat_number,
            'years_in_business' => $profile?->years_in_business,
            'operating_hours' => $profile?->operating_hours,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'minimum_order_amount' => $profile?->minimum_order_amount !== null
                ? (float) $profile->minimum_order_amount
                : null,
        ];
    }

    /**
     * Location & Address screen — read-only (GET display only).
     *
     * @param  list<string>  $locationParts
     * @return array<string, mixed>
     */
    private function locationAddress($profile, array $locationParts): array
    {
        return [
            'title' => 'Location & Address',
            'read_only' => true,
            'emirate' => $profile?->emirate,
            'city' => $profile?->city,
            'address' => $profile?->address,
            'google_maps_location' => $profile?->google_maps_location,
            'delivery_radius_km' => $profile?->delivery_radius !== null
                ? (float) $profile->delivery_radius
                : null,
            'location_display' => implode(', ', $locationParts) ?: null,
        ];
    }

    /**
     * Payment Methods screen — read-only (GET display only).
     *
     * @return array<string, mixed>
     */
    private function paymentMethods($profile): array
    {
        return [
            'title' => 'Payment Methods',
            'read_only' => true,
            'bank_name' => $profile?->bank_name,
            'iban' => $profile?->iban,
            'account_holder_name' => $profile?->account_holder_name,
        ];
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

    private function profilePictureUrl(Vendor $vendor, $user): ?string
    {
        $vendorPicture = $vendor->profile?->profile_picture_url;
        if ($vendorPicture !== null) {
            return $vendorPicture;
        }

        return ProfilePictureUploadService::fullUrl($user?->profile_picture);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseOperatingHours(?string $operatingHours): array
    {
        if ($operatingHours === null || trim($operatingHours) === '') {
            return [null, null];
        }

        if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', trim($operatingHours), $matches)) {
            return [$matches[1], $matches[2]];
        }

        return [null, null];
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
