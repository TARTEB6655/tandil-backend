<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_order_mappings') || $this->hasIndex('vnd_ord_map_vendor_created_idx')) {
            return;
        }

        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            $table->index(['vendor_id', 'created_at'], 'vnd_ord_map_vendor_created_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendor_order_mappings') || ! $this->hasIndex('vnd_ord_map_vendor_created_idx')) {
            return;
        }

        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            $table->dropIndex('vnd_ord_map_vendor_created_idx');
        });
    }

    private function hasIndex(string $indexName): bool
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            foreach ($conn->select("PRAGMA index_list('vendor_order_mappings')") as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return count($conn->select('SHOW INDEX FROM `vendor_order_mappings` WHERE Key_name = ?', [$indexName])) > 0;
    }
};
