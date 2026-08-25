<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_order_mappings', 'delivery_otp')) {
                $table->string('delivery_otp', 10)->nullable()->after('tracking_number');
            }
            if (! Schema::hasColumn('vendor_order_mappings', 'delivery_otp_expires_at')) {
                $table->timestamp('delivery_otp_expires_at')->nullable()->after('delivery_otp');
            }
            if (! Schema::hasColumn('vendor_order_mappings', 'delivery_otp_confirmed_at')) {
                $table->timestamp('delivery_otp_confirmed_at')->nullable()->after('delivery_otp_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            foreach (['delivery_otp_confirmed_at', 'delivery_otp_expires_at', 'delivery_otp'] as $col) {
                if (Schema::hasColumn('vendor_order_mappings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
