<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropUsersIndexIfExists('users_email_unique');
        $this->dropUsersIndexIfExists('users_phone_unique');

        // Avoid duplicate composite rows breaking the migration (same role + email).
        // Cross-role duplicates are intentional and allowed.

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['email', 'role'], 'users_email_role_unique');
            $table->unique(['phone', 'role'], 'users_phone_role_unique');
        });
    }

    public function down(): void
    {
        $this->dropUsersIndexIfExists('users_email_role_unique');
        $this->dropUsersIndexIfExists('users_phone_role_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email', 'users_email_unique');
            $table->unique('phone', 'users_phone_unique');
        });
    }

    private function dropUsersIndexIfExists(string $indexName): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                // SQLite: drop by Laravel name helper
                Schema::table('users', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });

                return;
            }

            $exists = DB::selectOne(
                'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                ['users', $indexName]
            );
            if ($exists && (int) ($exists->c ?? 0) > 0) {
                DB::statement('ALTER TABLE `users` DROP INDEX `'.$indexName.'`');
            }
        } catch (\Throwable) {
            try {
                Schema::table('users', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            } catch (\Throwable) {
                // Index may already be gone.
            }
        }
    }
};
