<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_availability', function (Blueprint $table) {
            $table->foreignId('default_bank_account_id')->nullable()->after('working_hours_slots')->constrained('technician_bank_accounts')->nullOnDelete();
            $table->string('payout_frequency', 20)->nullable()->after('default_bank_account_id'); // weekly, biweekly, monthly
        });
    }

    public function down(): void
    {
        Schema::table('technician_availability', function (Blueprint $table) {
            $table->dropForeign(['default_bank_account_id']);
            $table->dropColumn('payout_frequency');
        });
    }
};
