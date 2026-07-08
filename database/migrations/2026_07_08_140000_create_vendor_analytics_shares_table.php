<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_analytics_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('period', 16)->default('month');
            $table->string('file_path', 500);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_analytics_shares');
    }
};
