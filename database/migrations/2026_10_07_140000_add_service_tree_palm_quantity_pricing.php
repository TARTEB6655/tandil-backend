<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carts')) {
            Schema::table('carts', function (Blueprint $table) {
                if (! Schema::hasColumn('carts', 'tree_quantity')) {
                    $table->unsignedInteger('tree_quantity')->nullable()->after('required_area');
                }
                if (! Schema::hasColumn('carts', 'palm_tree_quantity')) {
                    $table->unsignedInteger('palm_tree_quantity')->nullable()->after('tree_quantity');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'tree_quantity')) {
                    $table->unsignedInteger('tree_quantity')->nullable()->after('required_area');
                }
                if (! Schema::hasColumn('order_items', 'palm_tree_quantity')) {
                    $table->unsignedInteger('palm_tree_quantity')->nullable()->after('tree_quantity');
                }
                if (! Schema::hasColumn('order_items', 'price_per_tree')) {
                    $table->decimal('price_per_tree', 12, 2)->nullable()->after('palm_tree_quantity');
                }
                if (! Schema::hasColumn('order_items', 'price_per_palm_tree')) {
                    $table->decimal('price_per_palm_tree', 12, 2)->nullable()->after('price_per_tree');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('carts')) {
            Schema::table('carts', function (Blueprint $table) {
                foreach (['palm_tree_quantity', 'tree_quantity'] as $col) {
                    if (Schema::hasColumn('carts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['price_per_palm_tree', 'price_per_tree', 'palm_tree_quantity', 'tree_quantity'] as $col) {
                    if (Schema::hasColumn('order_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
