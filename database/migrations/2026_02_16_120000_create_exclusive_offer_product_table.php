<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Links exclusive offers to products (many-to-many).
     */
    public function up(): void
    {
        Schema::create('exclusive_offer_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exclusive_offer_id')->constrained('exclusive_offers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unique(['exclusive_offer_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exclusive_offer_product');
    }
};
