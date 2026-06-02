<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_option_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('product_option_groups', 'subtitle')) {
                $table->string('subtitle')->nullable()->after('name');
            }
        });

        Schema::table('product_options', function (Blueprint $table) {
            if (! Schema::hasColumn('product_options', 'subtitle')) {
                $table->string('subtitle')->nullable()->after('label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            if (Schema::hasColumn('product_options', 'subtitle')) {
                $table->dropColumn('subtitle');
            }
        });

        Schema::table('product_option_groups', function (Blueprint $table) {
            if (Schema::hasColumn('product_option_groups', 'subtitle')) {
                $table->dropColumn('subtitle');
            }
        });
    }
};

