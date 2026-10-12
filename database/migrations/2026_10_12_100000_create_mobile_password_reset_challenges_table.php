<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'mobile_password_reset_challenges';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $this->defineColumns($table);
                $this->defineIndexes($table);
            });

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLE, 'user_id')) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn(self::TABLE, 'phone_digits')) {
                $table->string('phone_digits', 32);
            }
            if (! Schema::hasColumn(self::TABLE, 'portal')) {
                $table->string('portal', 32);
            }
            if (! Schema::hasColumn(self::TABLE, 'otp_hash')) {
                $table->string('otp_hash');
            }
            if (! Schema::hasColumn(self::TABLE, 'otp_expires_at')) {
                $table->timestamp('otp_expires_at');
            }
            if (! Schema::hasColumn(self::TABLE, 'otp_attempts')) {
                $table->unsignedTinyInteger('otp_attempts')->default(0);
            }
            if (! Schema::hasColumn(self::TABLE, 'send_count')) {
                $table->unsignedTinyInteger('send_count')->default(1);
            }
            if (! Schema::hasColumn(self::TABLE, 'last_sent_at')) {
                $table->timestamp('last_sent_at');
            }
            if (! Schema::hasColumn(self::TABLE, 'locked_until')) {
                $table->timestamp('locked_until')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'reset_token_hash')) {
                $table->string('reset_token_hash')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'reset_token_expires_at')) {
                $table->timestamp('reset_token_expires_at')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'request_ip')) {
                $table->string('request_ip', 45)->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'consumed_at')) {
                $table->timestamp('consumed_at')->nullable();
            }
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $this->defineIndexes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function defineColumns(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('phone_digits', 32);
        $table->string('portal', 32);
        $table->string('otp_hash');
        $table->timestamp('otp_expires_at');
        $table->unsignedTinyInteger('otp_attempts')->default(0);
        $table->unsignedTinyInteger('send_count')->default(1);
        $table->timestamp('last_sent_at');
        $table->timestamp('locked_until')->nullable();
        $table->string('reset_token_hash')->nullable();
        $table->timestamp('reset_token_expires_at')->nullable();
        $table->string('request_ip', 45)->nullable();
        $table->timestamp('consumed_at')->nullable();
        $table->timestamps();
    }

    private function defineIndexes(Blueprint $table): void
    {
        if (! $this->indexExists('mpw_ch_phone_portal_consumed')) {
            $table->index(['phone_digits', 'portal', 'consumed_at'], 'mpw_ch_phone_portal_consumed');
        }
        if (! $this->indexExists('mpw_ch_user_consumed')) {
            $table->index(['user_id', 'consumed_at'], 'mpw_ch_user_consumed');
        }
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
