<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_addresses') || Schema::hasColumn('user_addresses', 'type')) {
            return;
        }

        Schema::table('user_addresses', function (Blueprint $table) {
            $table->string('type', 50)->default('home')->after('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_addresses') || ! Schema::hasColumn('user_addresses', 'type')) {
            return;
        }

        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

