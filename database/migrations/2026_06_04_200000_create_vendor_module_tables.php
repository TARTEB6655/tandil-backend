<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('status', 32)->default('pending')->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('vendor_profiles')) {
            Schema::create('vendor_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('business_name');
                $table->string('owner_name');
                $table->string('email');
                $table->string('phone', 32)->nullable();
                $table->text('address')->nullable();
                $table->string('tax_vat_number', 64)->nullable();
                $table->string('logo_path')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_approval_logs')) {
            Schema::create('vendor_approval_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 32);
                $table->string('old_status', 32)->nullable();
                $table->string('new_status', 32);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['vendor_id', 'created_at'], 'vnd_appr_log_vendor_created_idx');
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'vendor_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('vendor_id')->nullable()->after('category_id')->constrained('vendors')->nullOnDelete();
                $table->index('vendor_id');
            });
        }

        if (! Schema::hasTable('vendor_products')) {
            Schema::create('vendor_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('status', 32)->default('active')->index();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['vendor_id', 'product_id'], 'vnd_prod_vendor_product_uniq');
                $table->unique('product_id', 'vnd_prod_product_uniq');
            });
        }

        if (! Schema::hasTable('vendor_product_prices')) {
            Schema::create('vendor_product_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_product_id')->constrained()->cascadeOnDelete();
                $table->decimal('price', 12, 2);
                $table->decimal('compare_at_price', 12, 2)->nullable();
                $table->string('currency', 8)->default('AED');
                $table->timestamp('effective_from')->useCurrent();
                $table->timestamp('effective_to')->nullable();
                $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_admin_override')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['vendor_product_id', 'effective_from'], 'vnd_price_vp_effective_idx');
            });
        }

        if (! Schema::hasTable('vendor_inventory')) {
            Schema::create('vendor_inventory', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_product_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(0);
                $table->unsignedInteger('low_stock_threshold')->default(5);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_inventory_logs')) {
            Schema::create('vendor_inventory_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_product_id')->constrained()->cascadeOnDelete();
                $table->string('change_type', 32);
                $table->integer('quantity_before');
                $table->integer('quantity_after');
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['vendor_product_id', 'created_at'], 'vnd_inv_log_vp_created_idx');
            });
        }

        if (! Schema::hasTable('vendor_order_mappings')) {
            Schema::create('vendor_order_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('status', 32)->default('pending')->index();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('shipping_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['order_id', 'vendor_id'], 'vnd_ord_map_order_vendor_uniq');
                $table->index(['vendor_id', 'status'], 'vnd_ord_map_vendor_status_idx');
            });
        }

        if (! Schema::hasTable('vendor_order_status_logs')) {
            Schema::create('vendor_order_status_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_order_mapping_id');
                $table->string('status', 32);
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->foreign('vendor_order_mapping_id', 'vnd_ord_stlog_map_fk')
                    ->references('id')
                    ->on('vendor_order_mappings')
                    ->cascadeOnDelete();
                $table->index(['vendor_order_mapping_id', 'created_at'], 'vnd_ord_stlog_map_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_order_status_logs');
        Schema::dropIfExists('vendor_order_mappings');
        Schema::dropIfExists('vendor_inventory_logs');
        Schema::dropIfExists('vendor_inventory');
        Schema::dropIfExists('vendor_product_prices');
        Schema::dropIfExists('vendor_products');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'vendor_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vendor_id');
            });
        }

        Schema::dropIfExists('vendor_approval_logs');
        Schema::dropIfExists('vendor_profiles');
        Schema::dropIfExists('vendors');
    }
};
