<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets') || Schema::hasColumn('support_tickets', 'email')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('email')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_tickets') || ! Schema::hasColumn('support_tickets', 'email')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};

