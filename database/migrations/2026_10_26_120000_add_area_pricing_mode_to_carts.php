<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'area_pricing_mode')) {
                $table->string('area_pricing_mode', 20)->nullable()->after('required_area');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'area_pricing_mode')) {
                $table->dropColumn('area_pricing_mode');
            }
        });
    }
};
