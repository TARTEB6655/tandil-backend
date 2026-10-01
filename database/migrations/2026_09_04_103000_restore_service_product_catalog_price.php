<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restore catalog price for live "service product" (id 211) after global m² sync overwrote it to 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where(function ($q) {
                $q->where('id', 211)
                    ->orWhere('sku', 'sku-003')
                    ->orWhere('handle', 'test-22');
            })
            ->where('name', 'service product')
            ->update([
                'price' => 100,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally empty — previous overwritten value (7) was incorrect.
    }
};
