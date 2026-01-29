<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type'); // financial, performance, customer, operational, user, subscription
            $table->string('status')->default('pending'); // pending, generated, scheduled, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->string('recurrence')->nullable(); // daily, weekly, monthly, yearly
            $table->timestamp('generated_at')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('format')->default('pdf'); // pdf, excel, csv
            $table->json('parameters')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_reports');
    }
};
