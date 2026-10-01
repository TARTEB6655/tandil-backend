<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'special_instructions')) {
                $table->text('special_instructions')->nullable()->after('refund_reason');
            }
        });

        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_mobile_checkouts', 'special_instructions')) {
                $table->text('special_instructions')->nullable()->after('total_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'special_instructions')) {
                $table->dropColumn('special_instructions');
            }
        });

        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (Schema::hasColumn('shop_mobile_checkouts', 'special_instructions')) {
                $table->dropColumn('special_instructions');
            }
        });
    }
};
