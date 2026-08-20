<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multiple products in one order can each have their own booking_date/booking_slot
     * (added on order_items), so one order can now produce multiple Visits — one per
     * order_item — instead of a single Visit per order.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained('order_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'order_item_id')) {
                $table->dropConstrainedForeignId('order_item_id');
            }
        });
    }
};
