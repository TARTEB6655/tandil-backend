<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_order_mappings', 'tracking_number')) {
                $table->string('tracking_number', 64)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_order_mappings', 'tracking_number')) {
                $table->dropColumn('tracking_number');
            }
        });
    }
};
