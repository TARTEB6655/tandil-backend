<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->enum('plan', ['1_month','3_month','6_month','12_month']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 10, 2);
            $table->string('payment_status')->default('pending');
            $table->integer('total_visits')->default(0);
            $table->integer('completed_visits')->default(0);
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
