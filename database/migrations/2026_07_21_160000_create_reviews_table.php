<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            return;
        }

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Null product_id = overall service rating for the order; set = per-product rating.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'order_id', 'product_id'], 'reviews_user_order_product_unique');
            $table->index(['product_id', 'rating'], 'reviews_product_rating_idx');
            $table->index('order_id', 'reviews_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
