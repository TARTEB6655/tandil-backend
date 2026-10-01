<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'Combined Package', 'type' => Package::TYPE_COMBINED, 'sort_order' => 0],
            ['name' => 'Fruit Basket Package', 'type' => Package::TYPE_FRUIT, 'sort_order' => 1],
            ['name' => 'Vegetable Basket Package', 'type' => Package::TYPE_VEGETABLE, 'sort_order' => 2],
        ];

        foreach ($packages as $p) {
            Package::firstOrCreate(
                ['type' => $p['type']],
                [
                    'name' => $p['name'],
                    'slug' => \Illuminate\Support\Str::slug($p['name']),
                    'price' => 0,
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $p['sort_order'],
                ]
            );
        }
    }
}
