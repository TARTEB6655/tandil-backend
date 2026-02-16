<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot: product can be linked to multiple services (optional).
     */
    public function up(): void
    {
        Schema::create('product_service', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->primary(['product_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_service');
    }
};
