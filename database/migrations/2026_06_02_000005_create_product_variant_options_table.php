<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Links each variant to the specific option choice that defines it.
        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                  ->constrained('product_variants')
                  ->cascadeOnDelete();
            $table->foreignId('product_option_id')
                  ->constrained('product_options')
                  ->cascadeOnDelete();
            $table->unique(['product_variant_id', 'product_option_id'], 'pvo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_options');
    }
};
