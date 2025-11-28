<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->date('scheduled_date');
            $table->string('status')->default('pending'); // pending, completed, reviewed
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('technician_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
