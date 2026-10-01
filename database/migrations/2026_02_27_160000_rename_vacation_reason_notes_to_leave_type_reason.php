<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_vacations', function (Blueprint $table) {
            $table->renameColumn('reason', 'leave_type');
            $table->renameColumn('notes', 'reason');
        });
    }

    public function down(): void
    {
        Schema::table('technician_vacations', function (Blueprint $table) {
            $table->renameColumn('reason', 'notes');
            $table->renameColumn('leave_type', 'reason');
        });
    }
};
