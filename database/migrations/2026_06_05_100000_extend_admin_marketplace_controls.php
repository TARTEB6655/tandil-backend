<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('verification_status', 32)->default('pending')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'type'], 'vnd_doc_vendor_type_idx');
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('rejection_reason');
            }
        });

        Schema::table('vendor_products', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_products', 'approval_status')) {
                $table->string('approval_status', 32)->default('pending')->after('status')->index();
                $table->text('rejection_reason')->nullable()->after('approval_status');
                $table->timestamp('approved_at')->nullable()->after('rejection_reason');
                $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_order_mappings', 'commission_amount')) {
                $table->decimal('commission_amount', 12, 2)->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('vendor_order_mappings', 'dispute_status')) {
                $table->string('dispute_status', 32)->nullable()->after('commission_amount')->index();
                $table->text('dispute_notes')->nullable()->after('dispute_status');
                $table->text('admin_notes')->nullable()->after('dispute_notes');
                $table->text('cancellation_reason')->nullable()->after('admin_notes');
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_order_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_order_mappings', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            foreach (['commission_amount', 'dispute_status', 'dispute_notes', 'admin_notes', 'cancellation_reason', 'cancelled_at'] as $col) {
                if (Schema::hasColumn('vendor_order_mappings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('vendor_products', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_products', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            foreach (['approval_status', 'rejection_reason', 'approved_at'] as $col) {
                if (Schema::hasColumn('vendor_products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'commission_rate')) {
                $table->dropColumn('commission_rate');
            }
        });

        Schema::dropIfExists('vendor_documents');
    }
};
