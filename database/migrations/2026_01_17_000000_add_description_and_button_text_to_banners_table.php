<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds description (subtitle) and button_text (CTA label) for app slider banners.
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->string('button_text')->nullable()->after('action_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['description', 'button_text']);
        });
    }
};
