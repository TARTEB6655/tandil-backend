<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_photos', function (Blueprint $table) {
            $table->boolean('show_on_client_app')->default(false)->after('photo_path');
        });

        // Keep existing photos visible until admin curates them.
        DB::table('visit_photos')->update(['show_on_client_app' => true]);
    }

    public function down(): void
    {
        Schema::table('visit_photos', function (Blueprint $table) {
            $table->dropColumn('show_on_client_app');
        });
    }
};
