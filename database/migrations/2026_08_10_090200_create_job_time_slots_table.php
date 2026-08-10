<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job Scheduling: "Time slots" screen. Admin-configured slots customers
     * can book from (e.g. 12:00 PM, 60 min).
     */
    public function up(): void
    {
        Schema::create('job_time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('start_time', 5); // HH:MM, 24h
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_time_slots');
    }
};
