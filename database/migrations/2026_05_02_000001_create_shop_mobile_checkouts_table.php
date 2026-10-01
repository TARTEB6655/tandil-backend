<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_mobile_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('checkout_ref', 64)->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('source', 16); // cart | buy_now
            $table->string('currency', 3)->default('aed');
            $table->unsignedInteger('amount_minor');
            $table->json('lines_json');
            $table->json('shipping_json');
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('tax_percent', 5, 2)->nullable();
            $table->decimal('shipping_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_mobile_checkouts');
    }
};
