<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class DemoCouponsSeeder extends Seeder
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
                'category_ids' => [],
            ],
            [
                'code' => 'FLAT20',
                'title' => 'AED 20 off',
                'description' => 'AED 20 off orders over AED 100. All products & services.',
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
                'catalog_scope' => Coupon::SCOPE_BOTH,
                'category_ids' => [],
            ],
            [
                'code' => 'WELCOME15',
                'title' => '15% off',
                'description' => '15% off orders over AED 80 (max AED 50 off).',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'min_order_amount' => 80,
                'max_discount_amount' => 50,
                'starts_at' => now()->subDays(30)->toDateString(),
                'ends_at' => null,
                'is_active' => true,
                'usage_limit' => null,
                'usage_limit_per_user' => 1,
                'applies_to' => Coupon::APPLIES_ALL,
                'catalog_scope' => Coupon::SCOPE_BOTH,
                'category_ids' => [],
            ],
            [
                'code' => 'FREESHIP',
                'title' => 'Free shipping',
                'description' => 'Free shipping on orders over AED 75.',
                'discount_type' => 'free_shipping',
                'discount_value' => 0,
                'min_order_amount' => 75,
                'max_discount_amount' => null,
                'starts_at' => now()->subDays(30)->toDateString(),
                'ends_at' => null,
                'is_active' => true,
                'usage_limit' => null,
                'usage_limit_per_user' => null,
                'applies_to' => Coupon::APPLIES_ALL,
                'catalog_scope' => Coupon::SCOPE_BOTH,
                'category_ids' => [],
            ],
            [
                'code' => 'EXPIRED',
                'title' => 'Inactive demo',
                'description' => 'Should always fail validation.',
                'discount_type' => 'percentage',
                'discount_value' => 5,
                'min_order_amount' => 0,
                'max_discount_amount' => null,
                'starts_at' => now()->subYear()->toDateString(),
                'ends_at' => now()->subMonth()->toDateString(),
                'is_active' => false,
                'usage_limit' => null,
                'usage_limit_per_user' => null,
                'applies_to' => Coupon::APPLIES_ALL,
                'catalog_scope' => Coupon::SCOPE_BOTH,
                'category_ids' => [],
            ],
        ];

        foreach ($rows as $row) {
            $categoryIds = $row['category_ids'];
            unset($row['category_ids']);

            $coupon = Coupon::query()->updateOrCreate(
                ['code' => $row['code']],
                $row
            );
            $coupon->categories()->sync($categoryIds);
        }
    }
}
