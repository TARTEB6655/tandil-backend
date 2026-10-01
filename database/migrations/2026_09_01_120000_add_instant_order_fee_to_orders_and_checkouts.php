<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'instant_order_fee')) {
                $table->decimal('instant_order_fee', 10, 2)->default(0)->after('shipping_amount');
            }
        });

        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_mobile_checkouts', 'instant_order_fee')) {
                $table->decimal('instant_order_fee', 10, 2)->default(0)->after('shipping_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'instant_order_fee')) {
                $table->dropColumn('instant_order_fee');
            }
        });

        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (Schema::hasColumn('shop_mobile_checkouts', 'instant_order_fee')) {
                $table->dropColumn('instant_order_fee');
            }
        });
    }
};
