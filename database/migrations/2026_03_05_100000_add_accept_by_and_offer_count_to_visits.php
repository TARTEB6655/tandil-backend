<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Job offer time limit: technician must accept by accept_by.
     * offer_count = how many technicians we've offered to (for escalation after 2-3).
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->timestamp('accept_by')->nullable()->after('escalated_at');
            $table->unsignedTinyInteger('offer_count')->default(0)->after('accept_by');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['accept_by', 'offer_count']);
        });
    }
};
