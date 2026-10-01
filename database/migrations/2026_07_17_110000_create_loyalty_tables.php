<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'loyalty_points_balance')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'wallet_forfeited_total')) {
                    $table->unsignedInteger('loyalty_points_balance')->default(0)->after('wallet_forfeited_total');
                } else {
                    $table->unsignedInteger('loyalty_points_balance')->default(0);
                }
            });
        }

        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('points_required');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title', 200);
            $table->unsignedInteger('points');
            $table->foreignId('loyalty_reward_id')->nullable()->constrained('loyalty_rewards')->nullOnDelete();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_rewards');

        if (Schema::hasColumn('users', 'loyalty_points_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('loyalty_points_balance');
            });
        }
    }
};
