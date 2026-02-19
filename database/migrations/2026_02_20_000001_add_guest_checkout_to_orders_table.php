<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_email', 255)->nullable()->after('user_id');
            $table->string('guest_full_name', 255)->nullable()->after('guest_email');
            $table->string('guest_phone', 20)->nullable()->after('guest_full_name');
            $table->string('guest_street_address', 500)->nullable()->after('guest_phone');
            $table->string('guest_city', 100)->nullable()->after('guest_street_address');
            $table->string('guest_state', 100)->nullable()->after('guest_city');
            $table->string('guest_zip_code', 20)->nullable()->after('guest_state');
            $table->string('guest_country', 100)->nullable()->after('guest_zip_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'guest_email', 'guest_full_name', 'guest_phone',
                'guest_street_address', 'guest_city', 'guest_state',
                'guest_zip_code', 'guest_country',
            ]);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
