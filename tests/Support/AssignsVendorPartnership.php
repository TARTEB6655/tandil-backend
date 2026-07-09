<?php

namespace Tests\Support;

use App\Models\Vendor;
use App\Models\VendorPartnership;
use App\Models\VendorPartnershipTier;
use Database\Seeders\VendorPartnershipTierSeeder;

trait AssignsVendorPartnership
{
    protected function seedPartnershipTiers(): void
    {
        $this->seed(VendorPartnershipTierSeeder::class);
    }

    protected function assignTestPartnership(Vendor $vendor, ?string $slug = 'diamond'): VendorPartnership
    {
        if (VendorPartnershipTier::count() === 0) {
            $this->seedPartnershipTiers();
        }

        $tier = VendorPartnershipTier::where('slug', $slug)->firstOrFail();

        return VendorPartnership::create([
            'vendor_id' => $vendor->id,
            'tier_id' => $tier->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths($tier->duration_months),
            'next_payment_at' => now()->addMonths($tier->duration_months),
        ]);
    }
}
