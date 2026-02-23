<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('status'); // low, medium, high, urgent
            $table->string('category')->nullable()->after('priority');     // general, billing, technical, account, order, other
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['priority', 'category']);
        });
    }
};
