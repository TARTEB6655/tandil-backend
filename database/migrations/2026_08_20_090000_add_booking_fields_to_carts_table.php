<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'booking_date')) {
                $table->date('booking_date')->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('carts', 'booking_slot')) {
                $table->string('booking_slot')->nullable()->after('booking_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'booking_slot')) {
                $table->dropColumn('booking_slot');
            }
            if (Schema::hasColumn('carts', 'booking_date')) {
                $table->dropColumn('booking_date');
            }
        });
    }
};
