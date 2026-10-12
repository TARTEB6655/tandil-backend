<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_password_reset_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone_digits', 32);
            $table->string('portal', 32);
            $table->string('otp_hash');
            $table->timestamp('otp_expires_at');
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->unsignedTinyInteger('send_count')->default(1);
            $table->timestamp('last_sent_at');
            $table->timestamp('locked_until')->nullable();
            $table->string('reset_token_hash')->nullable();
            $table->timestamp('reset_token_expires_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['phone_digits', 'portal', 'consumed_at']);
            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_password_reset_challenges');
    }
};
