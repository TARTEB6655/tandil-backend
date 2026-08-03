<?php

namespace Database\Seeders;

use App\Models\Emirate;
use App\Models\VendorType;
use Illuminate\Database\Seeder;

class VendorTypeAndEmirateSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Fruits', 'slug' => 'fruits'],
            ['name' => 'Vegetables', 'slug' => 'vegetables'],
            ['name' => 'Poultry', 'slug' => 'poultry'],
            ['name' => 'Seafood', 'slug' => 'seafood'],
            ['name' => 'Meat', 'slug' => 'meat'],
            ['name' => 'Honey', 'slug' => 'honey'],
            ['name' => 'Nuts', 'slug' => 'nuts'],
            ['name' => 'Restaurant', 'slug' => 'restaurant'],
            ['name' => 'Other', 'slug' => 'other'],
        ];

        foreach ($types as $row) {
            VendorType::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name'], 'is_active' => true]
            );
        }

        $emirates = [
            ['name' => 'Abu Dhabi', 'slug' => 'abu-dhabi'],
            ['name' => 'Dubai', 'slug' => 'dubai'],
            ['name' => 'Sharjah', 'slug' => 'sharjah'],
            ['name' => 'Ajman', 'slug' => 'ajman'],
            ['name' => 'Umm Al Quwain', 'slug' => 'umm-al-quwain'],
            ['name' => 'Ras Al Khaimah', 'slug' => 'ras-al-khaimah'],
            ['name' => 'Fujairah', 'slug' => 'fujairah'],
        ];

        foreach ($emirates as $row) {
            Emirate::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name'], 'is_active' => true]
            );
        }
    }
}
