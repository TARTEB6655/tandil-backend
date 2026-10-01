<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'priority')) {
                $table->string('priority')->default('medium')->after('status');
            }
            if (! Schema::hasColumn('support_tickets', 'category')) {
                $table->string('category')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('support_tickets', 'email')) {
                $table->string('email')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('support_tickets', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('support_tickets', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};

