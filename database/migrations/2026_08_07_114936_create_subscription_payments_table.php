<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending/completed Stripe PaymentIntents for client subscription renew/upgrade
 * (Membership checkout screen). Separate from wallet_topups and shop checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20); // renew|upgrade
            $table->string('from_plan', 20)->nullable();
            $table->string('to_plan', 20)->nullable(); // target plan for upgrade; same as from_plan for renew
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('amount_minor'); // fils for AED
            $table->string('currency', 10)->default('aed');
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('status', 30)->default('pending'); // pending|succeeded|failed|cancelled
            $table->string('payment_method', 30)->nullable(); // stripe|apple_pay (client preference)
            $table->timestamp('consumed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
