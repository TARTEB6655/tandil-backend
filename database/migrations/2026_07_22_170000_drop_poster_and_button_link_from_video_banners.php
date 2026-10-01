<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_banners', function (Blueprint $table) {
            if (Schema::hasColumn('video_banners', 'poster_path')) {
                $table->dropColumn('poster_path');
            }
            if (Schema::hasColumn('video_banners', 'button_link')) {
                $table->dropColumn('button_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('video_banners', function (Blueprint $table) {
            if (! Schema::hasColumn('video_banners', 'poster_path')) {
                $table->string('poster_path')->nullable()->after('video_path');
            }
            if (! Schema::hasColumn('video_banners', 'button_link')) {
                $table->string('button_link')->nullable()->after('button_text');
            }
        });
    }
};
