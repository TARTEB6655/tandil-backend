<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Add area_id column if it doesn't exist
            if (!Schema::hasColumn('visits', 'area_id')) {
                $table->foreignId('area_id')->nullable()->after('technician_id')->constrained('areas')->onDelete('set null');
            }
            
            // Add supervisor_id column if it doesn't exist
            if (!Schema::hasColumn('visits', 'supervisor_id')) {
                $table->foreignId('supervisor_id')->nullable()->after('area_id')->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'supervisor_id')) {
                $table->dropForeign(['supervisor_id']);
                $table->dropColumn('supervisor_id');
            }
            if (Schema::hasColumn('visits', 'area_id')) {
                $table->dropForeign(['area_id']);
                $table->dropColumn('area_id');
            }
        });
    }
};
