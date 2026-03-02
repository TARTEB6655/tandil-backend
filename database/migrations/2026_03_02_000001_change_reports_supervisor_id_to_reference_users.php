<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('supervisor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
        });
    }
};
