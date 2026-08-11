<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job Scheduling: separate admin-only "Internal notes" field for the
     * Booking detail (Jobs calendar) screen. Kept distinct from `notes`,
     * which stores the client-facing pipe-delimited job title/service string
     * (parsed by JobSchedulingController::jobTitleFromNotes) — reusing that
     * column here would silently overwrite the job title whenever an admin
     * saved internal notes.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'internal_notes')) {
                $table->dropColumn('internal_notes');
            }
        });
    }
};
