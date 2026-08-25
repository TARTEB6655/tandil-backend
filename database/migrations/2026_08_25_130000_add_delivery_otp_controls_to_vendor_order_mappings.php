<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_order_mappings', 'delivery_otp_sent_at')) {
                $table->timestamp('delivery_otp_sent_at')->nullable()->after('delivery_otp_confirmed_at');
            }
            if (! Schema::hasColumn('vendor_order_mappings', 'delivery_otp_sent_to')) {
                $table->string('delivery_otp_sent_to', 32)->nullable()->after('delivery_otp_sent_at');
            }
            if (! Schema::hasColumn('vendor_order_mappings', 'delivery_otp_attempts')) {
                $table->unsignedTinyInteger('delivery_otp_attempts')->default(0)->after('delivery_otp_sent_to');
            }
            if (! Schema::hasColumn('vendor_order_mappings', 'delivery_otp_locked_until')) {
                $table->timestamp('delivery_otp_locked_until')->nullable()->after('delivery_otp_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            foreach ([
                'delivery_otp_locked_until',
                'delivery_otp_attempts',
                'delivery_otp_sent_to',
                'delivery_otp_sent_at',
            ] as $col) {
                if (Schema::hasColumn('vendor_order_mappings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
