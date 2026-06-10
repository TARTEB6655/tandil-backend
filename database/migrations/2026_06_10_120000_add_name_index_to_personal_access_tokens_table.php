<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists('pat_tokenable_name_index')) {
            return;
        }

        // `name` is TEXT — full-column index exceeds MySQL key length; prefix lengths are enough
        // for Sanctum token names like api_client / api_vendor.
        DB::statement(
            'ALTER TABLE `personal_access_tokens` ADD INDEX `pat_tokenable_name_index` (`tokenable_type`(100), `tokenable_id`, `name`(64))'
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->indexExists('pat_tokenable_name_index')) {
            return;
        }

        DB::statement('ALTER TABLE `personal_access_tokens` DROP INDEX `pat_tokenable_name_index`');
    }

    private function indexExists(string $indexName): bool
    {
        $table = Schema::getConnection()->getTablePrefix().'personal_access_tokens';
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
