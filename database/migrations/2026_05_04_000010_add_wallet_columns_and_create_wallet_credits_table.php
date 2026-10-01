<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'wallet_balance')) {
                $table->decimal('wallet_balance', 12, 2)->default(0)->after('status');
            }
            if (! Schema::hasColumn('users', 'wallet_forfeited_total')) {
                $table->decimal('wallet_forfeited_total', 12, 2)->default(0)->after('wallet_balance');
            }
        });

        if (! Schema::hasTable('wallet_credits')) {
            Schema::create('wallet_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('reason', 100)->default('order_refund');
                $table->string('status', 30)->default('active'); // active|used|expired|forfeited
                $table->timestamp('credited_at');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('forfeited_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['status', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_credits');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'wallet_forfeited_total')) {
                $table->dropColumn('wallet_forfeited_total');
            }
            if (Schema::hasColumn('users', 'wallet_balance')) {
                $table->dropColumn('wallet_balance');
            }
        });
    }
};
