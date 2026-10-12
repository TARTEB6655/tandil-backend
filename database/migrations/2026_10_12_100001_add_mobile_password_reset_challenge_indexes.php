<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe follow-up when the table exists but indexes failed (MySQL identifier length).
 */
return new class extends Migration
{
    private const TABLE = 'mobile_password_reset_challenges';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (! $this->indexExists('mpw_ch_phone_portal_consumed')) {
                $table->index(['phone_digits', 'portal', 'consumed_at'], 'mpw_ch_phone_portal_consumed');
            }
            if (! $this->indexExists('mpw_ch_user_consumed')) {
                $table->index(['user_id', 'consumed_at'], 'mpw_ch_user_consumed');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if ($this->indexExists('mpw_ch_phone_portal_consumed')) {
                $table->dropIndex('mpw_ch_phone_portal_consumed');
            }
            if ($this->indexExists('mpw_ch_user_consumed')) {
                $table->dropIndex('mpw_ch_user_consumed');
            }
        });
    }

    private function indexExists(string $indexName): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `'.self::TABLE.'` WHERE Key_name = ?',
            [$indexName]
        );

        return $rows !== [];
    }
};
