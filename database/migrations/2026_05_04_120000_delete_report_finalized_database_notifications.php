<?php

use App\Notifications\ReportFinalized;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove stored ReportFinalized rows (product: no longer sent; old rows clutter inboxes).
     * Other notification types are untouched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')->where('type', ReportFinalized::class)->delete();
    }

    public function down(): void
    {
        // Irreversible: deleted rows are not restored.
    }
};
