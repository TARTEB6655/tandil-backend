<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'wallet_amount_applied')) {
                $table->decimal('wallet_amount_applied', 12, 2)->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('orders', 'wallet_redeemed_at')) {
                $table->timestamp('wallet_redeemed_at')->nullable()->after('paid_at');
            }
        });

        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_mobile_checkouts', 'wallet_amount_applied')) {
                $table->decimal('wallet_amount_applied', 12, 2)->default(0)->after('total_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'wallet_redeemed_at')) {
                $table->dropColumn('wallet_redeemed_at');
            }
            if (Schema::hasColumn('orders', 'wallet_amount_applied')) {
                $table->dropColumn('wallet_amount_applied');
            }
        });

        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (Schema::hasColumn('shop_mobile_checkouts', 'wallet_amount_applied')) {
                $table->dropColumn('wallet_amount_applied');
            }
        });
    }
};
