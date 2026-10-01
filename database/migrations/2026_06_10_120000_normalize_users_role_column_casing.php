<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE users SET role = LOWER(role) WHERE role IS NOT NULL AND role != LOWER(role)');
    }

    public function down(): void
    {
        // Irreversible data normalization.
    }
};
