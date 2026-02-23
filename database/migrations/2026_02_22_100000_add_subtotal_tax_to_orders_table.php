<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tax-exclusive: subtotal (items only), tax = subtotal × tax_percent%, total = subtotal + shipping + tax.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal_amount', 10, 2)->nullable()->after('total_amount');
            $table->decimal('tax_amount', 10, 2)->nullable()->after('subtotal_amount');
            $table->decimal('tax_percent', 5, 2)->nullable()->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal_amount', 'tax_amount', 'tax_percent']);
        });
    }
};
