<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'booking_date')) {
                $table->date('booking_date')->nullable()->after('subtotal');
            }
            if (! Schema::hasColumn('order_items', 'booking_slot')) {
                $table->string('booking_slot')->nullable()->after('booking_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'booking_slot')) {
                $table->dropColumn('booking_slot');
            }
            if (Schema::hasColumn('order_items', 'booking_date')) {
                $table->dropColumn('booking_date');
            }
        });
    }
};
