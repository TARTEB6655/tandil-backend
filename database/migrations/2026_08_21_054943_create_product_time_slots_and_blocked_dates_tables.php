<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // NULL = recurring on every bookable day; set = only this calendar date
            $table->date('date')->nullable();
            $table->string('start_time', 8); // HH:mm
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'date', 'start_time'], 'product_time_slots_unique');
            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'date']);
        });

        Schema::create('product_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('date');
            $table->string('block_type', 20); // full_day | time_slot
            $table->string('time', 8)->nullable(); // HH:mm when time_slot
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_blocked_dates');
        Schema::dropIfExists('product_time_slots');
    }
};
