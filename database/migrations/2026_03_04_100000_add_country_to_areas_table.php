<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Areas = cities; link to country (e.g. UAE).
     */
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->string('country', 100)->default('UAE')->after('description')->comment('Country (e.g. UAE); areas are cities within this country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
