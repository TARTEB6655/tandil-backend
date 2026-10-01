<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VendorDemoOrdersCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $orderIds = Order::query()
            ->where('special_instructions', 'like', VendorDemoOrdersSeeder::DEMO_MARKER.'%')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            $this->command->info('No demo vendor orders found to remove.');

            return;
        }

        $mappingIds = VendorOrderMapping::query()
            ->whereIn('order_id', $orderIds)
            ->pluck('id');

        DB::transaction(function () use ($orderIds, $mappingIds) {
            if ($mappingIds->isNotEmpty()) {
                VendorOrderStatusLog::query()
                    ->whereIn('vendor_order_mapping_id', $mappingIds)
                    ->delete();

                VendorOrderMapping::query()
                    ->whereIn('id', $mappingIds)
                    ->delete();
            }

            Order::query()
                ->whereIn('id', $orderIds)
                ->each(function (Order $order) {
                    $order->items()->delete();
                    $order->delete();
                });
        });

        $this->command->info('Removed '.$orderIds->count().' demo vendor order(s) and related mappings.');
    }
}
