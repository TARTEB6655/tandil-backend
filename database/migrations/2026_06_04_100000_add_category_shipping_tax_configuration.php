<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'shipping_amount') && ! Schema::hasColumn('categories', 'shipping_cost')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->renameColumn('shipping_amount', 'shipping_cost');
            });
        }

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('categories', 'shipping_type')) {
                $table->string('shipping_type', 16)->nullable()->after('shipping_cost');
            }
            if (! Schema::hasColumn('categories', 'tax_percentage')) {
                $table->decimal('tax_percentage', 5, 2)->nullable()->after('shipping_type');
            }
        });

        if (Schema::hasColumn('categories', 'delivery_type') && Schema::hasColumn('categories', 'shipping_type')) {
            DB::table('categories')
                ->whereNull('shipping_type')
                ->whereNotNull('delivery_type')
                ->update(['shipping_type' => DB::raw('delivery_type')]);

            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('delivery_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'tax_percentage')) {
                $table->dropColumn('tax_percentage');
            }
            if (Schema::hasColumn('categories', 'shipping_type')) {
                $table->dropColumn('shipping_type');
            }
        });

        if (Schema::hasColumn('categories', 'shipping_cost') && ! Schema::hasColumn('categories', 'shipping_amount')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->renameColumn('shipping_cost', 'shipping_amount');
            });
        }
    }
};
