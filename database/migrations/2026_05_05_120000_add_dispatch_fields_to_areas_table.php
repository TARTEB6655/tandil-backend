<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            if (! Schema::hasColumn('areas', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('country');
            }
            if (! Schema::hasColumn('areas', 'priority')) {
                $table->unsignedInteger('priority')->default(100)->after('is_active');
            }
            if (! Schema::hasColumn('areas', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('priority');
            }
            if (! Schema::hasColumn('areas', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('areas', 'service_radius_km')) {
                $table->decimal('service_radius_km', 8, 2)->default(30)->after('longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            foreach (['service_radius_km', 'longitude', 'latitude', 'priority', 'is_active'] as $col) {
                if (Schema::hasColumn('areas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

