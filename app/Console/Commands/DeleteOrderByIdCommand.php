<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\Review;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Models\Visit;
use App\Models\VisitOffer;
use App\Models\VisitPhoto;
use App\Models\VisitSupervisorDecline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete one shop order and its vendor mapping / visits / related rows.
 *
 * Example (order 58):
 *   php artisan orders:delete 58 --dry-run
 *   php artisan orders:delete 58 --force
 */
class DeleteOrderByIdCommand extends Command
{
    protected $signature = 'orders:delete
                            {id : Shop order id (e.g. 58)}
                            {--dry-run : Show what would be deleted}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a single shop order (vendor mappings, visits, items, related notifications).';

    public function handle(): int
    {
        $orderId = (int) $this->argument('id');
        if ($orderId <= 0) {
            $this->error('Invalid order id.');

            return self::FAILURE;
        }

        $order = Order::query()->find($orderId);
        if (! $order) {
            $this->error("Order #{$orderId} not found.");

            return self::FAILURE;
        }

        $mappingIds = VendorOrderMapping::query()
            ->where('order_id', $orderId)
            ->pluck('id')
            ->all();

        $visitIds = Visit::query()
            ->where(function ($q) use ($orderId) {
                $q->where('order_id', $orderId)
                    ->orWhere('notes', 'like', '%[SHOP-ORDER:'.$orderId.']%');
            })
            ->pluck('id')
            ->all();

        $itemCount = Schema::hasTable('order_items')
            ? OrderItem::query()->where('order_id', $orderId)->count()
            : 0;

        $this->warn("Order #{$orderId}");
        $this->line('  payment_status: '.(string) $order->payment_status);
        $this->line('  order_status: '.(string) $order->order_status);
        $this->line('  total_amount: '.(string) $order->total_amount);
        $this->line('  vendor_mappings: '.count($mappingIds).(count($mappingIds) ? ' ['.implode(', ', $mappingIds).']' : ''));
        $this->line('  visits: '.count($visitIds).(count($visitIds) ? ' ['.implode(', ', $visitIds).']' : ''));
        $this->line('  order_items: '.$itemCount);

        if ($this->option('dry-run')) {
            $this->info('Dry run only — nothing deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete order #{$orderId} and related data now?", false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orderId, $mappingIds, $visitIds) {
            if ($visitIds !== []) {
                if (Schema::hasTable('visit_supervisor_declines')) {
                    VisitSupervisorDecline::query()->whereIn('visit_id', $visitIds)->delete();
                }
                if (Schema::hasTable('visit_photos')) {
                    VisitPhoto::query()->whereIn('visit_id', $visitIds)->delete();
                }
                if (Schema::hasTable('visit_offers')) {
                    VisitOffer::query()->whereIn('visit_id', $visitIds)->delete();
                }
                if (Schema::hasTable('reports')) {
                    Report::query()->whereIn('visit_id', $visitIds)->delete();
                }
                if (Schema::hasTable('complaints')) {
                    Complaint::query()->whereIn('visit_id', $visitIds)->delete();
                }
                Visit::query()->whereIn('id', $visitIds)->delete();
            }

            if ($mappingIds !== []) {
                if (Schema::hasTable('vendor_order_status_logs')) {
                    VendorOrderStatusLog::query()
                        ->whereIn('vendor_order_mapping_id', $mappingIds)
                        ->delete();
                }
                VendorOrderMapping::query()->whereIn('id', $mappingIds)->delete();
            }

            if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'order_id')) {
                Review::query()->where('order_id', $orderId)->delete();
            }

            if (Schema::hasTable('wallet_credits') && Schema::hasColumn('wallet_credits', 'order_id')) {
                DB::table('wallet_credits')->where('order_id', $orderId)->update(['order_id' => null]);
            }

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')
                    ->where('data', 'like', '%"order_id":'.$orderId.'%')
                    ->orWhere('data', 'like', '%"order_id": '.$orderId.'%')
                    ->delete();
            }

            if (Schema::hasTable('order_items')) {
                OrderItem::query()->where('order_id', $orderId)->delete();
            }

            Order::query()->whereKey($orderId)->delete();
        });

        $this->info("Deleted order #{$orderId}.");

        return self::SUCCESS;
    }
}
