<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_profiles', 'banner_path')) {
                $table->string('banner_path', 500)->nullable()->after('logo_path');
            }
            if (! Schema::hasColumn('vendor_profiles', 'social_links')) {
                $table->json('social_links')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            foreach (['banner_path', 'social_links'] as $column) {
                if (Schema::hasColumn('vendor_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
