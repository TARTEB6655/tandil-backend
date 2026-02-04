<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes to improve API query performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Products table indexes
        Schema::table('products', function (Blueprint $table) {
            // Index for category filtering and ordering
            if (!$this->hasIndex('products', 'products_category_id_id_index')) {
                $table->index(['category_id', 'id'], 'products_category_id_id_index');
            }
            // Index for stock filtering
            if (!$this->hasIndex('products', 'products_stock_index')) {
                $table->index('stock', 'products_stock_index');
            }
            // Index for name search
            if (!$this->hasIndex('products', 'products_name_index')) {
                $table->index('name', 'products_name_index');
            }
        });

        // Product images table indexes
        Schema::table('product_images', function (Blueprint $table) {
            // Index for finding primary image quickly
            if (!$this->hasIndex('product_images', 'product_images_product_id_is_primary_index')) {
                $table->index(['product_id', 'is_primary'], 'product_images_product_id_is_primary_index');
            }
        });

        // Categories table - id is already primary key, no additional indexes needed
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_id_id_index');
            $table->dropIndex('products_stock_index');
            $table->dropIndex('products_name_index');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_product_id_is_primary_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();
        
        if ($driver === 'sqlite') {
            $indexes = $conn->select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }
        
        // MySQL/MariaDB
        $indexes = $conn->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
