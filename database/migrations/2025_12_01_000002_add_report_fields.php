<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (! Schema::hasColumn('reports', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('reports', 'recommended_products')) {
                $table->json('recommended_products')->nullable();
            }
            if (! Schema::hasColumn('reports', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (! Schema::hasColumn('reports', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('reports', 'status')) {
                $table->string('status')->default('pending');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('reports', 'recommended_products')) {
                $table->dropColumn('recommended_products');
            }
            if (Schema::hasColumn('reports', 'approved_by')) {
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('reports', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('reports', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
