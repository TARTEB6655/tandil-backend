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
use Database\Seeders\VendorDemoOrdersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete shop orders that belong to vendor products (and their vendor mappings).
 * Platform-only / subscription orders without vendor lines are left untouched.
 *
 * Production:
 *   php artisan vendor:purge-orders --dry-run
 *   php artisan vendor:purge-orders --force
 *   php artisan vendor:purge-orders --demo-only --force
 *   php artisan vendor:purge-orders --vendor=12 --force
 */
class PurgeVendorOrdersCommand extends Command
{
    protected $signature = 'vendor:purge-orders
                            {--vendor= : Limit to one vendor id}
                            {--demo-only : Only remove seeded demo vendor orders ([DEMO_VENDOR_ORDER])}
                            {--dry-run : Show counts / sample IDs only}
                            {--force : Required in production / skip confirmation}';

    protected $description = 'Delete old vendor-product shop orders + vendor_order_mappings (platform catalog orders kept)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force') && ! $this->option('dry-run')) {
            $this->error('Production requires --force (or use --dry-run first).');

            return self::FAILURE;
        }

        $vendorId = $this->option('vendor') ? (int) $this->option('vendor') : null;
        $demoOnly = (bool) $this->option('demo-only');

        $orderIds = $this->matchingOrderIds($vendorId, $demoOnly);
        $count = $orderIds->count();

        if ($count === 0) {
            $this->info('No matching vendor orders found.');

            return self::SUCCESS;
        }

        $mappingCount = VendorOrderMapping::query()
            ->whereIn('order_id', $orderIds)
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->count();

        $scope = $demoOnly ? 'DEMO vendor orders' : 'vendor-product orders';
        $vendorLabel = $vendorId ? "vendor_id={$vendorId}" : 'all vendors';

        $this->warn("Matched {$count} {$scope} ({$vendorLabel}), mappings={$mappingCount}");
        $this->line('Sample order IDs: '.$orderIds->take(20)->implode(', '));

        if ($this->option('dry-run')) {
            $this->info('Dry run only — nothing deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete these {$count} order(s) now?", false)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($orderIds->chunk(100) as $chunk) {
            foreach ($chunk as $orderId) {
                $this->deleteOrder((int) $orderId, $vendorId);
                $deleted++;
            }
        }

        $remainingMappings = VendorOrderMapping::query()
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->count();

        $this->info("Deleted {$deleted} order(s). Remaining vendor_order_mappings".($vendorId ? " for vendor {$vendorId}" : '')."={$remainingMappings}");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, int>
     */
    private function matchingOrderIds(?int $vendorId, bool $demoOnly): Collection
    {
        if ($demoOnly) {
            $marker = VendorDemoOrdersSeeder::DEMO_MARKER;

            return Order::query()
                ->where('special_instructions', 'like', $marker.'%')
                ->when($vendorId, function ($q) use ($vendorId) {
                    $q->whereHas('vendorMappings', fn ($mq) => $mq->where('vendor_id', $vendorId));
                })
                ->orderBy('id')
                ->pluck('id');
        }

        // Orders that have a vendor mapping and/or line items on vendor-owned products
        $fromMappings = VendorOrderMapping::query()
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->pluck('order_id');

        $fromItems = Order::query()
            ->whereNull('package_id')
            ->whereHas('items.product', function ($q) use ($vendorId) {
                $q->whereNotNull('vendor_id');
                if ($vendorId) {
                    $q->where('vendor_id', $vendorId);
                }
            })
            ->pluck('id');

        return $fromMappings->merge($fromItems)->unique()->sort()->values();
    }

    private function deleteOrder(int $orderId, ?int $vendorId): void
    {
        $order = Order::query()->find($orderId);
        if (! $order) {
            return;
        }

        $mappingQuery = VendorOrderMapping::query()->where('order_id', $orderId);
        if ($vendorId) {
            $mappingQuery->where('vendor_id', $vendorId);
        }
        $mappingIds = $mappingQuery->pluck('id')->all();

        // If filtering by one vendor and the order also has other vendors / platform lines,
        // only strip that vendor's mapping when other lines remain.
        $otherVendorMappings = VendorOrderMapping::query()
            ->where('order_id', $orderId)
            ->when($vendorId, fn ($q) => $q->where('vendor_id', '!=', $vendorId))
            ->exists();

        $hasPlatformLines = OrderItem::query()
            ->where('order_id', $orderId)
            ->whereHas('product', fn ($q) => $q->whereNull('vendor_id'))
            ->exists();

        $deleteWholeOrder = ! $vendorId || (! $otherVendorMappings && ! $hasPlatformLines);

        DB::transaction(function () use ($orderId, $mappingIds, $deleteWholeOrder, $vendorId) {
            if ($deleteWholeOrder) {
                $visitIds = Visit::query()
                    ->where(function ($q) use ($orderId) {
                        $q->where('order_id', $orderId)
                            ->orWhere('notes', 'like', '%[SHOP-ORDER:'.$orderId.']%');
                    })
                    ->pluck('id')
                    ->all();

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
            }

            if ($mappingIds !== []) {
                if (Schema::hasTable('vendor_order_status_logs')) {
                    VendorOrderStatusLog::query()
                        ->whereIn('vendor_order_mapping_id', $mappingIds)
                        ->delete();
                }
                VendorOrderMapping::query()->whereIn('id', $mappingIds)->delete();
            }

            if (! $deleteWholeOrder) {
                // Remove only this vendor's line items
                if ($vendorId && Schema::hasTable('order_items')) {
                    OrderItem::query()
                        ->where('order_id', $orderId)
                        ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
                        ->delete();
                }

                return;
            }

            if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'order_id')) {
                Review::query()->where('order_id', $orderId)->delete();
            }

            if (Schema::hasTable('wallet_credits') && Schema::hasColumn('wallet_credits', 'order_id')) {
                DB::table('wallet_credits')->where('order_id', $orderId)->update(['order_id' => null]);
            }

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')
                    ->where(function ($q) use ($orderId) {
                        $q->where('data', 'like', '%"order_id":'.$orderId.'%')
                            ->orWhere('data', 'like', '%"order_id": '.$orderId.'%');
                    })
                    ->delete();
            }

            if (Schema::hasTable('order_items')) {
                OrderItem::query()->where('order_id', $orderId)->delete();
            }

            Order::query()->whereKey($orderId)->delete();
        });
    }
}
