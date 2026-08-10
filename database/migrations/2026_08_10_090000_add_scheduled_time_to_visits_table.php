<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job Scheduling: optional time-of-day slot chosen alongside scheduled_date
     * (e.g. "12:00"). Nullable/backward compatible — existing visits with no
     * time keep working exactly as before.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'scheduled_time')) {
                $table->string('scheduled_time', 5)->nullable()->after('scheduled_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'scheduled_time')) {
                $table->dropColumn('scheduled_time');
            }
        });
    }
};
