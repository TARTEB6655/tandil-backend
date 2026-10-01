<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'estimated_arrival')) {
                $table->string('estimated_arrival', 255)->nullable()->after('weight_unit');
            }
            if (! Schema::hasColumn('products', 'job_duration')) {
                $table->string('job_duration', 255)->nullable()->after('estimated_arrival');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'job_duration')) {
                $table->dropColumn('job_duration');
            }
            if (Schema::hasColumn('products', 'estimated_arrival')) {
                $table->dropColumn('estimated_arrival');
            }
        });
    }
};
