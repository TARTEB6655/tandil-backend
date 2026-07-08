<?php

namespace App\Services\Vendor;

use App\Models\Vendor;

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

        [$opensAt, $closesAt] = $this->parseOperatingHours($profile?->operating_hours);

        return [
            'header' => [
                'name' => $profile?->owner_name ?: $user?->name,
                'subtitle' => $this->headerSubtitle($profile?->business_name, $locationParts),
            ],
            'summary' => [
                'profile_image_url' => $vendor->logo_url ?: $user?->profile_picture_url,
                'banner_url' => $profile?->banner_url,
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
            ],
            'edit_profile' => $this->editProfileForm($vendor, $profile, $user, $opensAt, $closesAt),
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
     * Edit Profile screen — same shape for GET and POST response.
     *
     * @return array<string, mixed>
     */
    private function editProfileForm(Vendor $vendor, $profile, $user, ?string $opensAt, ?string $closesAt): array
    {
        return [
            'title' => 'Edit Profile',
            'subtitle' => 'Update contact and store operations',
            'store_branding' => [
                'logo_url' => $vendor->logo_url,
                'hint' => 'Update your business profile logo.',
            ],
            'editable' => [
                'business_name' => $profile?->business_name,
                'owner_name' => $profile?->owner_name,
                'phone' => $profile?->phone ?: $user?->phone,
                'address' => $profile?->address,
                'city' => $profile?->city,
                'description' => $profile?->description,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'delivery_radius' => $profile?->delivery_radius !== null
                    ? (float) $profile->delivery_radius
                    : null,
                'minimum_order_amount' => $profile?->minimum_order_amount !== null
                    ? (float) $profile->minimum_order_amount
                    : null,
                'bank_name' => $profile?->bank_name,
                'iban' => $profile?->iban,
                'account_holder_name' => $profile?->account_holder_name,
            ],
            'verified_by_admin' => [
                'email' => $profile?->email ?: $user?->email,
                'vendor_type' => $profile?->vendor_type,
                'vendor_type_label' => $profile?->vendor_type_label,
                'emirate' => $profile?->emirate,
                'trade_license_number' => $profile?->trade_license_number,
                'locked' => true,
                'note' => 'These fields are set during registration. Contact support to request changes.',
            ],
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
