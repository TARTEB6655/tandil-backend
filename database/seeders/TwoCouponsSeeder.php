<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Quick seed: 2 demo coupons for empty DB (SAVE10 + FLAT20).
 * Run: php artisan db:seed --class=TwoCouponsSeeder
 */
class TwoCouponsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'SAVE10',
                'title' => '10% off',
                'description' => '10% off orders over AED 50 (max AED 30 off). All store products.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'min_order_amount' => 50,
                'max_discount_amount' => 30,
                'starts_at' => now()->subDays(30)->toDateString(),
                'ends_at' => null,
                'is_active' => true,
                'usage_limit' => null,
                'usage_limit_per_user' => 3,
                'applies_to' => Coupon::APPLIES_ALL,
                'catalog_scope' => Coupon::SCOPE_PRODUCTS,
            ],
            [
                'code' => 'FLAT20',
                'title' => 'AED 20 off',
                'description' => 'AED 20 off orders over AED 100.',
                'discount_type' => 'fixed_amount',
                'discount_value' => 20,
                'min_order_amount' => 100,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(30)->toDateString(),
                'ends_at' => null,
                'is_active' => true,
                'usage_limit' => null,
                'usage_limit_per_user' => null,
                'applies_to' => Coupon::APPLIES_ALL,
                'catalog_scope' => Coupon::SCOPE_PRODUCTS,
            ],
        ];

        foreach ($rows as $row) {
            $coupon = Coupon::query()->updateOrCreate(
                ['code' => $row['code']],
                $row
            );
            $coupon->categories()->sync([]);
            $coupon->services()->sync([]);
        }

        $this->command?->info('Seeded coupons: SAVE10, FLAT20');
    }
}
