<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['categories', 'services', 'emirates', 'areas', 'contractor_cities'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'contractor_signup_enabled')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $after = Schema::hasColumn($table, 'is_active') ? 'is_active' : null;
                if ($after) {
                    $blueprint->boolean('contractor_signup_enabled')->default(true)->after($after);
                } else {
                    $blueprint->boolean('contractor_signup_enabled')->default(true);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['categories', 'services', 'emirates', 'areas', 'contractor_cities'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'contractor_signup_enabled')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('contractor_signup_enabled');
                });
            }
        }
    }
};
