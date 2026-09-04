<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restore catalog price for product 212 (معاينة و رفع المقاسات) after global m² sync overwrote it to 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where(function ($q) {
                $q->where('id', 212)
                    ->orWhere('sku', 'SERVICE-INSPECTION-001')
                    ->orWhere('handle', 'inspection-measurement');
            })
            ->update([
                'price' => 120,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Previous overwritten value (7) was incorrect — no rollback.
    }
};
