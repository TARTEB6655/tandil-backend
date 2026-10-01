<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job Scheduling: "Blocked dates & slots" screen. block_type=full_day closes
     * the whole date; block_type=time_slot closes just one time (time column set).
     */
    public function up(): void
    {
        Schema::create('job_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('block_type', 20)->default('full_day'); // full_day | time_slot
            $table->string('time', 5)->nullable(); // HH:MM, required when block_type=time_slot
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['date', 'block_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_blocked_dates');
    }
};
