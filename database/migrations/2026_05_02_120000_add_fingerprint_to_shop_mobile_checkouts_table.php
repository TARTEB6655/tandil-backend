<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            $table->dropIndex(['fingerprint']);
            $table->dropColumn('fingerprint');
        });
    }
};
