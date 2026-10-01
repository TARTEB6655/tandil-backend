<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep line-item / mapping product titles after catalog products are deleted.
 * Previously order_items.product_id cascaded, wiping calendar names.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('product_name')->nullable()->after('product_id');
            });
        }

        if (Schema::hasTable('order_items') && Schema::getConnection()->getDriverName() !== 'sqlite') {
            $this->relaxOrderItemProductForeignKey();
        }

        if (Schema::hasTable('vendor_order_mappings') && ! Schema::hasColumn('vendor_order_mappings', 'product_title')) {
            Schema::table('vendor_order_mappings', function (Blueprint $table) {
                $table->string('product_title')->nullable()->after('vendor_id');
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'product_name')) {
            DB::table('order_items')
                ->whereNull('product_name')
                ->whereNotNull('product_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $name = DB::table('products')->where('id', $row->product_id)->value('name');
                        if (is_string($name) && trim($name) !== '') {
                            DB::table('order_items')->where('id', $row->id)->update([
                                'product_name' => trim($name),
                            ]);
                        }
                    }
                });
        }

        if (Schema::hasTable('vendor_order_mappings') && Schema::hasColumn('vendor_order_mappings', 'product_title')) {
            $mappings = DB::table('vendor_order_mappings')
                ->whereNull('product_title')
                ->orderBy('id')
                ->get(['id', 'order_id', 'vendor_id']);

            foreach ($mappings as $mapping) {
                $names = DB::table('order_items as oi')
                    ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
                    ->where('oi.order_id', $mapping->order_id)
                    ->orderBy('oi.id')
                    ->get(['oi.product_name', 'p.name as live_name', 'p.vendor_id']);

                $titles = [];
                foreach ($names as $line) {
                    if ((int) ($line->vendor_id ?? 0) > 0 && (int) $line->vendor_id !== (int) $mapping->vendor_id) {
                        continue;
                    }
                    $title = trim((string) ($line->product_name ?: $line->live_name ?: ''));
                    if ($title !== '' && strcasecmp($title, 'Product') !== 0) {
                        $titles[$title] = true;
                    }
                }

                if ($titles === []) {
                    continue;
                }

                DB::table('vendor_order_mappings')->where('id', $mapping->id)->update([
                    'product_title' => implode(', ', array_keys($titles)),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendor_order_mappings') && Schema::hasColumn('vendor_order_mappings', 'product_title')) {
            Schema::table('vendor_order_mappings', function (Blueprint $table) {
                $table->dropColumn('product_title');
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'product_name')) {
            // Only drop if this migration added it on an older create (not recreate-from-scratch).
            // Safe on production upgrades; fresh installs already have the column from create.
        }
    }

    private function relaxOrderItemProductForeignKey(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }
};
