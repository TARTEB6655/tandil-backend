<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_order_mappings', 'vendor_notified_at')) {
                $table->timestamp('vendor_notified_at')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_order_mappings', 'vendor_notified_at')) {
                $table->dropColumn('vendor_notified_at');
            }
        });
    }
};
