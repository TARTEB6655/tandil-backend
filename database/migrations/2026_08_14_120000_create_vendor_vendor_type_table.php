<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_vendor_type')) {
            Schema::create('vendor_vendor_type', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_type_id')->constrained('vendor_types')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['vendor_id', 'vendor_type_id']);
            });
        }

        // Backfill from the existing single-value vendor_profiles.vendor_type column
        // so vendors registered before multi-select was supported keep their type.
        if (Schema::hasTable('vendor_profiles') && Schema::hasTable('vendor_types')) {
            $rows = DB::table('vendor_profiles')
                ->whereNotNull('vendor_type')
                ->where('vendor_type', '!=', '')
                ->get(['vendor_id', 'vendor_type']);

            $typeIdsBySlug = DB::table('vendor_types')->pluck('id', 'slug');
            $now = now();

            foreach ($rows as $row) {
                $typeId = $typeIdsBySlug[$row->vendor_type] ?? null;
                if ($typeId === null) {
                    continue;
                }

                DB::table('vendor_vendor_type')->insertOrIgnore([
                    'vendor_id' => $row->vendor_id,
                    'vendor_type_id' => $typeId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_vendor_type');
    }
};
