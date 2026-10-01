<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MembershipSeeder extends Seeder
{
    /**
     * Seed only membership (packages) data. Used by GET /api/client/memberships.
     */
    public function run(): void
    {
        $memberships = [
            [
                'name'        => 'Basic Membership',
                'type'        => Package::TYPE_COMBINED,
                'price'       => 99.00,
                'description' => 'Monthly combined fruit and vegetable delivery. Perfect for individuals.',
                'sort_order'  => 0,
            ],
            [
                'name'        => 'Premium Membership',
                'type'        => Package::TYPE_COMBINED,
                'price'       => 199.00,
                'description' => 'Premium monthly box with seasonal fruits and vegetables. Best value for families.',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Fruit Lover',
                'type'        => Package::TYPE_FRUIT,
                'price'       => 149.00,
                'description' => 'Monthly fruit-only subscription. Fresh and hand-picked.',
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Vegetable Box',
                'type'        => Package::TYPE_VEGETABLE,
                'price'       => 129.00,
                'description' => 'Monthly vegetable box. Organic options available.',
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Family Pack',
                'type'        => Package::TYPE_COMBINED,
                'price'       => 279.00,
                'description' => 'Large combined box for families. Free delivery on orders above AED 250.',
                'sort_order'  => 4,
            ],
        ];

        foreach ($memberships as $m) {
            $slug = Str::slug($m['name']);
            Package::firstOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $m['name'],
                    'type'        => $m['type'],
                    'price'       => $m['price'],
                    'description' => $m['description'],
                    'image'       => null,
                    'is_active'   => true,
                    'sort_order'  => $m['sort_order'],
                ]
            );
        }

        $this->command->info('Membership (packages) data seeded.');
    }
}
