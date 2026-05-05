<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32); // paypal, stripe, ...
            $table->string('provider_method_id', 191); // vaulted token/id from gateway
            $table->string('provider_customer_id', 191)->nullable(); // payer_id/customer id
            $table->string('label', 191)->nullable(); // e.g. PayPal john@example.com
            $table->string('brand', 64)->nullable();
            $table->string('last4', 8)->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->string('email', 191)->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'provider_method_id']);
            $table->index(['user_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payment_methods');
    }
};

