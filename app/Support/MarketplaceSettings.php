<?php

namespace App\Support;

use App\Models\Setting;

final class MarketplaceSettings
{
    public static function commissionPercent(): float
    {
        $v = Setting::get('marketplace_commission_percent', '10');

        return round((float) $v, 2);
    }

    public static function productApprovalRequired(): bool
    {
        return Setting::get('marketplace_product_approval_required', '1') === '1';
    }

    public static function setCommissionPercent(float $percent): void
    {
        Setting::set('marketplace_commission_percent', (string) round(max(0, min(100, $percent)), 2), 'text', 'marketplace');
    }

    public static function setProductApprovalRequired(bool $required): void
    {
        Setting::set('marketplace_product_approval_required', $required ? '1' : '0', 'boolean', 'marketplace');
    }

    public static function effectiveCommissionForVendor(?\App\Models\Vendor $vendor): float
    {
        if ($vendor && $vendor->commission_rate !== null) {
            return round((float) $vendor->commission_rate, 2);
        }

        return self::commissionPercent();
    }
}
