<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_online')->default(true);
            $table->boolean('auto_accept_jobs')->default(false);
            $table->json('working_days')->nullable(); // e.g. ["mon","tue","wed","thu","fri"]
            $table->json('working_hours_slots')->nullable(); // e.g. [{"slot":"morning","start":"08:00","end":"12:00"},...]
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_availability');
    }
};
