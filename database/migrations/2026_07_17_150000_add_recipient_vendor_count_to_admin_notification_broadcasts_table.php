<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notification_broadcasts', function (Blueprint $table) {
            $table->unsignedInteger('recipient_vendor_count')->default(0)->after('recipient_hr_count');
        });
    }

    public function down(): void
    {
        Schema::table('admin_notification_broadcasts', function (Blueprint $table) {
            $table->dropColumn('recipient_vendor_count');
        });
    }
};
