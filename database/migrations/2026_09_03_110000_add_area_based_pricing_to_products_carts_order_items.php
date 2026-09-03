<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'pricing_type')) {
                // fixed = one total; per_m2 = price per square meter (services only)
                $table->string('pricing_type', 20)->default('fixed')->after('price');
            }
            if (! Schema::hasColumn('products', 'price_includes')) {
                $table->json('price_includes')->nullable()->after('pricing_type');
            }
        });

        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'required_area')) {
                $table->decimal('required_area', 12, 2)->nullable()->after('unit_price');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'pricing_type')) {
                $table->string('pricing_type', 20)->default('fixed')->after('quantity');
            }
            if (! Schema::hasColumn('order_items', 'required_area')) {
                $table->decimal('required_area', 12, 2)->nullable()->after('pricing_type');
            }
            if (! Schema::hasColumn('order_items', 'price_includes')) {
                $table->json('price_includes')->nullable()->after('required_area');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'price_includes')) {
                $table->dropColumn('price_includes');
            }
            if (Schema::hasColumn('products', 'pricing_type')) {
                $table->dropColumn('pricing_type');
            }
        });

        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'required_area')) {
                $table->dropColumn('required_area');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            foreach (['price_includes', 'required_area', 'pricing_type'] as $col) {
                if (Schema::hasColumn('order_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
