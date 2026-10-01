<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_signup_requests', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });

        Schema::table('technician_signup_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id')->nullable()->change();
            $table->text('service_area')->change();
        });

        Schema::table('technician_signup_requests', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('technician_signup_requests', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });

        Schema::table('technician_signup_requests', function (Blueprint $table) {
            $table->string('service_area', 255)->change();
            $table->unsignedBigInteger('area_id')->nullable(false)->change();
        });

        Schema::table('technician_signup_requests', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('areas')->cascadeOnDelete();
        });
    }
};
