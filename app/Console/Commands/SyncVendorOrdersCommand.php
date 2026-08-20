<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\VendorOrderMapping;
use App\Services\Vendor\VendorOrderSyncService;
use Database\Seeders\VendorDemoOrdersSeeder;
use Illuminate\Console\Command;

class SyncVendorOrdersCommand extends Command
{
    protected $signature = 'vendor:sync-orders
                            {--paid-only : Only sync orders with payment_status=paid}
                            {--dry-run : Show how many shop orders would be synced}
                            {--cleanup-demo : Also remove demo vendor orders marked [DEMO_VENDOR_ORDER]}';

    protected $description = 'Backfill vendor_order_mappings from shop orders that contain vendor products (fixes admin/vendor order lists).';

    public function handle(VendorOrderSyncService $sync): int
    {
        if ($this->option('cleanup-demo')) {
            $this->cleanupDemoOrders();
        }

        $before = VendorOrderMapping::query()->count();

        $query = Order::query()
            ->whereNull('package_id')
            ->whereHas('items.product', fn ($q) => $q->whereNotNull('vendor_id'));

        if ($this->option('paid-only')) {
            $query->where('payment_status', 'paid');
        }

        $total = (clone $query)->count();
        $this->info("Shop orders with vendor products: {$total}");
        $this->info("Existing vendor_order_mappings: {$before}");

        if ($this->option('dry-run')) {
            $this->info('Dry run only — no sync performed.');

            return self::SUCCESS;
        }

        $synced = 0;
        $query->with(['items.product'])->orderBy('id')->chunkById(100, function ($orders) use ($sync, &$synced) {
            foreach ($orders as $order) {
                $sync->syncFromOrder($order);
                $synced++;
            }
        });

        $after = VendorOrderMapping::query()->count();
        $this->info("Synced {$synced} shop order(s).");
        $this->info("vendor_order_mappings now: {$after} (was {$before}).");

        return self::SUCCESS;
    }

    private function cleanupDemoOrders(): void
    {
        $marker = VendorDemoOrdersSeeder::DEMO_MARKER;
        $orderIds = Order::query()
            ->where('special_instructions', 'like', $marker.'%')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            $this->info('No demo vendor orders found to clean up.');

            return;
        }

        $mappingIds = VendorOrderMapping::query()->whereIn('order_id', $orderIds)->pluck('id');

        \Illuminate\Support\Facades\DB::transaction(function () use ($orderIds, $mappingIds) {
            if ($mappingIds->isNotEmpty()) {
                \App\Models\VendorOrderStatusLog::query()
                    ->whereIn('vendor_order_mapping_id', $mappingIds)
                    ->delete();
                VendorOrderMapping::query()->whereIn('id', $mappingIds)->delete();
            }

            Order::query()->whereIn('id', $orderIds)->each(function (Order $order) {
                $order->items()->delete();
                $order->delete();
            });
        });

        $this->warn('Removed '.$orderIds->count().' demo shop order(s) and '.$mappingIds->count().' vendor mapping(s).');
    }
}
