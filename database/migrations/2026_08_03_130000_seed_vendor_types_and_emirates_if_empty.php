<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures production servers that already ran the create migration
 * (empty tables) get the default vendor types + emirates rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_types') || ! Schema::hasTable('emirates')) {
            return;
        }

        $now = now();

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
            if (DB::table('vendor_types')->where('slug', $row['slug'])->exists()) {
                continue;
            }

            DB::table('vendor_types')->insert([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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
            if (DB::table('emirates')->where('slug', $row['slug'])->exists()) {
                continue;
            }

            DB::table('emirates')->insert([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep data — admin may have customized rows.
    }
};
