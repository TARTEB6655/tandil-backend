<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'extra_emails')) {
                $table->json('extra_emails')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'extra_phones')) {
                $table->json('extra_phones')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'extra_emails')) {
                $table->dropColumn('extra_emails');
            }
            if (Schema::hasColumn('users', 'extra_phones')) {
                $table->dropColumn('extra_phones');
            }
        });
    }
};

