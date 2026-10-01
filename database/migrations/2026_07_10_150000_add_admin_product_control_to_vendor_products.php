<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_products', function (Blueprint $table) {
            $table->boolean('disabled_by_admin')->default(false)->after('approved_by');
            $table->timestamp('disabled_by_admin_at')->nullable()->after('disabled_by_admin');
            $table->foreignId('disabled_by_admin_by')->nullable()->after('disabled_by_admin_at')->constrained('users')->nullOnDelete();
            $table->string('admin_disable_reason')->nullable()->after('disabled_by_admin_by');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disabled_by_admin_by');
            $table->dropColumn(['disabled_by_admin', 'disabled_by_admin_at', 'admin_disable_reason']);
        });
    }
};
