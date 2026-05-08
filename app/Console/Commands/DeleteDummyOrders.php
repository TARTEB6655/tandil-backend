<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class DeleteDummyOrders extends Command
{
    protected $signature = 'orders:delete-dummy
                            {--all : Delete all orders (not just dummy/test)}
                            {--dry-run : Show count and sample IDs only}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete dummy/test orders safely (or all orders with --all).';

    public function handle(): int
    {
        $deleteAll = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        $query = $deleteAll ? Order::query() : $this->dummyOrdersQuery();
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info($deleteAll ? 'No orders found to delete.' : 'No dummy/test orders found to delete.');

            return self::SUCCESS;
        }

        $sampleIds = (clone $query)->orderBy('id')->limit(20)->pluck('id')->all();
        $this->line('Matched order count: <info>' . $count . '</info>');
        $this->line('Sample order IDs: <comment>' . implode(', ', $sampleIds) . '</comment>');

        if ($dryRun) {
            $this->info('Dry run complete. No data deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $message = $deleteAll
                ? 'This will DELETE ALL orders from database. Continue?'
                : 'This will DELETE matched dummy/test orders. Continue?';

            if (! $this->confirm($message, false)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $deleted = 0;
        // Delete in chunks to avoid large single-query locks.
        (clone $query)->orderBy('id')->chunkById(500, function ($orders) use (&$deleted) {
            $ids = $orders->pluck('id')->all();
            if (! empty($ids)) {
                $deleted += Order::query()->whereIn('id', $ids)->delete();
            }
        });

        $this->info("Deleted {$deleted} order(s).");

        return self::SUCCESS;
    }

    private function dummyOrdersQuery(): Builder
    {
        return Order::query()->where(function (Builder $q) {
            $q->where('guest_email', 'like', '%test%')
                ->orWhere('guest_email', 'like', '%dummy%')
                ->orWhere('guest_email', 'like', '%example.com%')
                ->orWhere('guest_full_name', 'like', '%test%')
                ->orWhere('guest_full_name', 'like', '%dummy%')
                ->orWhere('payment_reference', 'like', 'test%')
                ->orWhere('payment_reference', 'like', 'demo%')
                ->orWhere('payment_reference', 'like', 'dummy%')
                ->orWhere('transaction_id', 'like', 'test%')
                ->orWhere('transaction_id', 'like', 'demo%')
                ->orWhere('transaction_id', 'like', 'dummy%')
                ->orWhere('special_instructions', 'like', '%dummy%')
                ->orWhere('special_instructions', 'like', '%test%')
                ->orWhereHas('user', function (Builder $uq) {
                    $uq->where('email', 'like', '%test%')
                        ->orWhere('email', 'like', '%dummy%')
                        ->orWhere('email', 'like', '%example.com%')
                        ->orWhere('name', 'like', '%test%')
                        ->orWhere('name', 'like', '%dummy%');
                });
        });
    }
}
