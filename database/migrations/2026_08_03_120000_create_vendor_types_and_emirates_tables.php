<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_types')) {
            Schema::create('vendor_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('emirates')) {
            Schema::create('emirates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('emirates');
        Schema::dropIfExists('vendor_types');
    }

    private function seedDefaults(): void
    {
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
            $existing = DB::table('vendor_types')->where('slug', $row['slug'])->first();
            if ($existing) {
                DB::table('vendor_types')->where('id', $existing->id)->update([
                    'name' => $row['name'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('vendor_types')->insert([
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
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
            $existing = DB::table('emirates')->where('slug', $row['slug'])->first();
            if ($existing) {
                DB::table('emirates')->where('id', $existing->id)->update([
                    'name' => $row['name'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('emirates')->insert([
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
