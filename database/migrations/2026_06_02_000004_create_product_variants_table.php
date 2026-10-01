<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A variant is a specific combination of option choices for a variable product.
        // For products where variants are not needed (e.g. the base "najdi weight" option
        // affects only the price), the option's price_modifier is enough and this table
        // can hold a single "base" variant per product to store sku/stock/price overrides.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100)->nullable();
            $table->decimal('price', 10, 2)->nullable(); // null = inherit product base price
            $table->integer('stock')->default(0);
            $table->boolean('is_default')->default(false);
            $table->string('label', 500)->nullable(); // human-readable combination string
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
