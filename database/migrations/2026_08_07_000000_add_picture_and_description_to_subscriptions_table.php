<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'picture')) {
                $table->string('picture', 1000)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('subscriptions', 'description')) {
                $table->text('description')->nullable()->after('picture');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('subscriptions', 'picture')) {
                $table->dropColumn('picture');
            }
        });
    }
};
