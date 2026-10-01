<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for Client, Technician, and Supervisor API hot paths.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                $this->addIndexIfMissing('visits', $table, ['technician_id', 'scheduled_date'], 'visits_technician_scheduled_idx');
                $this->addIndexIfMissing('visits', $table, ['technician_id', 'status'], 'visits_technician_status_idx');
                $this->addIndexIfMissing('visits', $table, ['area_id', 'status', 'created_at'], 'visits_area_status_created_idx');
                $this->addIndexIfMissing('visits', $table, ['supervisor_id', 'status'], 'visits_supervisor_status_idx');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $this->addIndexIfMissing('orders', $table, ['user_id', 'created_at'], 'orders_user_created_idx');
                $this->addIndexIfMissing('orders', $table, ['user_id', 'order_status'], 'orders_user_status_idx');
                if (Schema::hasColumn('orders', 'guest_email')) {
                    $this->addIndexIfMissing('orders', $table, 'guest_email', 'orders_guest_email_idx');
                }
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $this->addIndexIfMissing(
                    'notifications',
                    $table,
                    ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                    'notifications_inbox_idx'
                );
            });
        }

        if (Schema::hasTable('reports')) {
            Schema::table('reports', function (Blueprint $table) {
                $this->addIndexIfMissing('reports', $table, ['supervisor_id', 'status', 'created_at'], 'reports_supervisor_status_created_idx');
                $this->addIndexIfMissing('reports', $table, 'visit_id', 'reports_visit_id_idx');
            });
        }

        if (Schema::hasTable('area_supervisor')) {
            Schema::table('area_supervisor', function (Blueprint $table) {
                $this->addIndexIfMissing('area_supervisor', $table, 'user_id', 'area_supervisor_user_id_idx');
            });
        }

        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $this->addIndexIfMissing('leave_requests', $table, ['user_id', 'start_date', 'end_date'], 'leave_requests_user_dates_idx');
            });
        }

        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                $this->addIndexIfMissing('complaints', $table, ['visit_id', 'status'], 'complaints_visit_status_idx');
            });
        }

        if (Schema::hasTable('wallet_credits')) {
            Schema::table('wallet_credits', function (Blueprint $table) {
                $this->addIndexIfMissing('wallet_credits', $table, ['user_id', 'order_id'], 'wallet_credits_user_order_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'visits_technician_scheduled_idx');
                $this->dropIndexIfExists($table, 'visits_technician_status_idx');
                $this->dropIndexIfExists($table, 'visits_area_status_created_idx');
                $this->dropIndexIfExists($table, 'visits_supervisor_status_idx');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'orders_user_created_idx');
                $this->dropIndexIfExists($table, 'orders_user_status_idx');
                $this->dropIndexIfExists($table, 'orders_guest_email_idx');
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'notifications_inbox_idx');
            });
        }

        if (Schema::hasTable('reports')) {
            Schema::table('reports', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'reports_supervisor_status_created_idx');
                $this->dropIndexIfExists($table, 'reports_visit_id_idx');
            });
        }

        if (Schema::hasTable('area_supervisor')) {
            Schema::table('area_supervisor', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'area_supervisor_user_id_idx');
            });
        }

        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'leave_requests_user_dates_idx');
            });
        }

        if (Schema::hasTable('complaints')) {
            Schema::table('complaints', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'complaints_visit_status_idx');
            });
        }

        if (Schema::hasTable('wallet_credits')) {
            Schema::table('wallet_credits', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'wallet_credits_user_order_idx');
            });
        }
    }

    private function addIndexIfMissing(string $tableName, Blueprint $table, array|string $columns, string $name): void
    {
        if ($this->hasIndex($tableName, $name)) {
            return;
        }

        $table->index($columns, $name);
    }

    private function dropIndexIfExists(Blueprint $table, string $name): void
    {
        $tableName = method_exists($table, 'getTable') ? $table->getTable() : '';
        if ($tableName !== '' && ! $this->hasIndex($tableName, $name)) {
            return;
        }

        try {
            $table->dropIndex($name);
        } catch (\Throwable $e) {
            //
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $conn->select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $indexes = $conn->select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$indexName]);

        return count($indexes) > 0;
    }
};
