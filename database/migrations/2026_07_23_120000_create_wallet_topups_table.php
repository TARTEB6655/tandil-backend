<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending Stripe PaymentIntents for client wallet top-ups (Add Money).
 * Completely separate from shop_mobile_checkouts / product-service checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_topups')) {
            return;
        }

        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('amount_minor'); // fils for AED
            $table->string('currency', 10)->default('aed');
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('status', 30)->default('pending'); // pending|succeeded|failed|cancelled
            $table->string('payment_method', 30)->nullable(); // stripe|apple_pay (client preference)
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('wallet_credit_id')->nullable()->constrained('wallet_credits')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topups');
    }
};
