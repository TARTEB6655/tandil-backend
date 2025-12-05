<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->morphs('transactionable'); // Creates transactionable_type and transactionable_id
            $table->string('type')->default('payment'); // payment, refund, chargeback
            $table->string('gateway')->nullable(); // stripe, paypal, razorpay
            $table->string('payment_method')->nullable(); // card, bank_transfer, etc
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('AED');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->string('gateway_transaction_id')->nullable();
            $table->text('gateway_response')->nullable(); // JSON response from gateway
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // morphs() already creates an index for transactionable_type and transactionable_id
            $table->index('status');
            $table->index('gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
