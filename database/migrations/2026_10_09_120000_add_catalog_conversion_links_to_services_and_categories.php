<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('converted_to_category_id')->nullable()->after('category_id');
            $table->index('converted_to_category_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('converted_to_service_id')->nullable()->after('parent_id');
            $table->index('converted_to_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['converted_to_service_id']);
            $table->dropColumn('converted_to_service_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['converted_to_category_id']);
            $table->dropColumn('converted_to_category_id');
        });
    }
};
