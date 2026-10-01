<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'estimated_arrival')) {
                $table->string('estimated_arrival', 255)->nullable()->after('special_instructions');
            }
            if (! Schema::hasColumn('orders', 'job_duration')) {
                $table->string('job_duration', 255)->nullable()->after('estimated_arrival');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'job_duration')) {
                $table->dropColumn('job_duration');
            }
            if (Schema::hasColumn('orders', 'estimated_arrival')) {
                $table->dropColumn('estimated_arrival');
            }
        });
    }
};
