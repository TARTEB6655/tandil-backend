<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['weekly', 'monthly', 'seasonal', 'general'])->default('general');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('language', 10)->default('en'); // en, ar, ur
            $table->timestamp('scheduled_at')->nullable(); // For scheduled tips
            $table->unsignedBigInteger('created_by')->nullable(); // Admin who created it
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
