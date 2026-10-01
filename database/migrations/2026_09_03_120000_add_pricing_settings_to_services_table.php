<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'pricing_type')) {
                $table->string('pricing_type', 20)->default('fixed')->after('sort_order');
            }
            if (! Schema::hasColumn('services', 'price')) {
                // Fixed total OR price per m² for this service (applies to linked service products)
                $table->decimal('price', 10, 2)->nullable()->after('pricing_type');
            }
            if (! Schema::hasColumn('services', 'price_includes')) {
                $table->json('price_includes')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            foreach (['price_includes', 'price', 'pricing_type'] as $col) {
                if (Schema::hasColumn('services', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
