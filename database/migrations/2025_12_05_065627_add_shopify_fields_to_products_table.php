<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('vendor')->nullable()->after('name');
            $table->string('type')->nullable()->after('vendor');
            $table->string('sku')->nullable()->unique()->after('type');
            $table->string('barcode')->nullable()->after('sku');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft')->after('stock');
            $table->boolean('track_quantity')->default(true)->after('status');
            $table->boolean('allow_backorder')->default(false)->after('track_quantity');
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            $table->decimal('cost_per_item', 10, 2)->nullable()->after('compare_at_price');
            $table->string('weight')->nullable()->after('cost_per_item');
            $table->string('weight_unit')->default('kg')->after('weight');
            $table->text('tags')->nullable()->after('description');
            $table->string('meta_title')->nullable()->after('tags');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('handle')->nullable()->unique()->after('meta_description');
            $table->boolean('requires_shipping')->default(true)->after('weight_unit');
            $table->boolean('taxable')->default(true)->after('requires_shipping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'vendor',
                'type',
                'sku',
                'barcode',
                'status',
                'track_quantity',
                'allow_backorder',
                'compare_at_price',
                'cost_per_item',
                'weight',
                'weight_unit',
                'tags',
                'meta_title',
                'meta_description',
                'handle',
                'requires_shipping',
                'taxable',
            ]);
        });
    }
};
