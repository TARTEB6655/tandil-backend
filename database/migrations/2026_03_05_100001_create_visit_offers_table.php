<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tracks each offer to a technician (pending / accepted / rejected / timeout).
     */
    public function up(): void
    {
        Schema::create('visit_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->onDelete('cascade');
            $table->foreignId('technician_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('offered_at');
            $table->timestamp('accept_by')->nullable();
            $table->string('status', 20)->default('pending'); // pending, accepted, rejected, timeout
            $table->text('reject_reason')->nullable();
            $table->timestamps();
            $table->index(['visit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_offers');
    }
};
