<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job Scheduling: explicit job duration in minutes, set when admin picks a
     * custom Start/End range on the Booking detail (Jobs calendar) screen.
     * Nullable — when absent, duration is inferred from the matching
     * job_time_slots row (or defaults to 60 min), exactly as before.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->nullable()->after('scheduled_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'duration_minutes')) {
                $table->dropColumn('duration_minutes');
            }
        });
    }
};
