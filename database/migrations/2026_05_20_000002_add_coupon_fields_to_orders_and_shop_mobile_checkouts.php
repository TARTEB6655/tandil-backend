<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('user_id')->constrained('coupons')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code', 64)->nullable()->after('coupon_id');
            }
            if (! Schema::hasColumn('orders', 'coupon_discount_amount')) {
                $table->decimal('coupon_discount_amount', 12, 2)->default(0)->after('coupon_code');
            }
        });

        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_mobile_checkouts', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('user_id')->constrained('coupons')->nullOnDelete();
            }
            if (! Schema::hasColumn('shop_mobile_checkouts', 'coupon_code')) {
                $table->string('coupon_code', 64)->nullable()->after('coupon_id');
            }
            if (! Schema::hasColumn('shop_mobile_checkouts', 'coupon_merchandise_discount')) {
                $table->decimal('coupon_merchandise_discount', 12, 2)->default(0)->after('coupon_code');
            }
            if (! Schema::hasColumn('shop_mobile_checkouts', 'coupon_shipping_discount')) {
                $table->decimal('coupon_shipping_discount', 12, 2)->default(0)->after('coupon_merchandise_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (Schema::hasColumn('shop_mobile_checkouts', 'coupon_shipping_discount')) {
                $table->dropColumn('coupon_shipping_discount');
            }
            if (Schema::hasColumn('shop_mobile_checkouts', 'coupon_merchandise_discount')) {
                $table->dropColumn('coupon_merchandise_discount');
            }
            if (Schema::hasColumn('shop_mobile_checkouts', 'coupon_code')) {
                $table->dropColumn('coupon_code');
            }
            if (Schema::hasColumn('shop_mobile_checkouts', 'coupon_id')) {
                $table->dropConstrainedForeignId('coupon_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'coupon_discount_amount')) {
                $table->dropColumn('coupon_discount_amount');
            }
            if (Schema::hasColumn('orders', 'coupon_code')) {
                $table->dropColumn('coupon_code');
            }
            if (Schema::hasColumn('orders', 'coupon_id')) {
                $table->dropConstrainedForeignId('coupon_id');
            }
        });
    }
};
