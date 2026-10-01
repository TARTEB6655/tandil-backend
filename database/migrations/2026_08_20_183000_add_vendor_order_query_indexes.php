<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_order_mappings')) {
            return;
        }

        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (! $this->hasIndex('vnd_ord_map_vendor_status_idx')) {
                $table->index(['vendor_id', 'status'], 'vnd_ord_map_vendor_status_idx');
            }
            if (! $this->hasIndex('vnd_ord_map_vendor_order_idx')) {
                $table->index(['vendor_id', 'order_id'], 'vnd_ord_map_vendor_order_idx');
            }
        });

        if (Schema::hasTable('products')
            && Schema::hasColumn('products', 'vendor_id')
            && ! $this->hasIndexOn('products', 'products_vendor_id_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('vendor_id', 'products_vendor_id_index');
            });
        }

        if (Schema::hasTable('order_items')
            && ! $this->hasIndexOn('order_items', 'order_items_order_id_product_id_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['order_id', 'product_id'], 'order_items_order_id_product_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendor_order_mappings')) {
            Schema::table('vendor_order_mappings', function (Blueprint $table) {
                if ($this->hasIndex('vnd_ord_map_vendor_status_idx')) {
                    $table->dropIndex('vnd_ord_map_vendor_status_idx');
                }
                if ($this->hasIndex('vnd_ord_map_vendor_order_idx')) {
                    $table->dropIndex('vnd_ord_map_vendor_order_idx');
                }
            });
        }

        if (Schema::hasTable('products') && $this->hasIndexOn('products', 'products_vendor_id_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_vendor_id_index');
            });
        }

        if (Schema::hasTable('order_items') && $this->hasIndexOn('order_items', 'order_items_order_id_product_id_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex('order_items_order_id_product_id_index');
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        return $this->hasIndexOn('vendor_order_mappings', $indexName);
    }

    private function hasIndexOn(string $table, string $indexName): bool
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            foreach ($conn->select("PRAGMA index_list('{$table}')") as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return count($conn->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])) > 0;
    }
};
