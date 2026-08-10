<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job Scheduling: "Working hours & capacity" screen. Single-row settings
     * table (like a singleton config), mirrors the working_hours_slots JSON
     * shape already used by technician_availability.working_hours_slots.
     */
    public function up(): void
    {
        Schema::create('job_scheduling_settings', function (Blueprint $table) {
            $table->id();
            $table->json('working_hours')->nullable();
            $table->unsignedInteger('max_bookings_per_slot')->default(2);
            $table->unsignedInteger('max_bookings_per_day')->default(12);
            $table->unsignedInteger('buffer_minutes')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_scheduling_settings');
    }
};
