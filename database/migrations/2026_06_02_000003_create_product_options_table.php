<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per selectable choice inside an option group,
        // e.g. "in bag", "in box" (inside "Packaging type") or "8 قطع" inside "cutting"
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_group_id')
                  ->constrained('product_option_groups')
                  ->cascadeOnDelete();
            $table->string('label');              // display text, e.g. "in bag"
            $table->decimal('price_modifier', 10, 2)->default(0); // +/- on base price
            $table->string('image_path', 500)->nullable();         // optional thumbnail
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
