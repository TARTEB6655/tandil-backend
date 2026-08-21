<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Report;
use App\Models\Review;
use App\Models\Visit;
use App\Models\VisitOffer;
use App\Models\VisitPhoto;
use App\Models\VisitSupervisorDecline;
use App\Models\VendorOrderMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe client shop/test clutter: all visits (jobs) and all orders.
 *
 * Does NOT delete client user accounts, products, areas, or supervisors.
 *
 * Local / staging:
 *   php artisan clients:purge-orders-visits --dry-run
 *   php artisan clients:purge-orders-visits --force
 *
 * Production (on the server after deploy):
 *   php artisan clients:purge-orders-visits --force
 */
class PurgeClientOrdersAndVisitsCommand extends Command
{
    protected $signature = 'clients:purge-orders-visits
                            {--dry-run : Count only; delete nothing}
                            {--force : Required in production; skip confirmation}';

    protected $description = 'Delete ALL visits (jobs) and ALL orders (plus related reports/items). Keeps users/products.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force') && ! $this->option('dry-run')) {
            $this->error('Production requires --force (or use --dry-run first).');

            return self::FAILURE;
        }

        $visitCount = Visit::query()->count();
        $orderCount = Order::query()->count();
        $itemCount = Schema::hasTable('order_items') ? OrderItem::query()->count() : 0;

        $this->warn('About to purge client job/order data:');
        $this->line("  visits:      {$visitCount}");
        $this->line("  orders:      {$orderCount}");
        $this->line("  order_items: {$itemCount}");
        $this->line('  (+ reports, photos, offers, declines, complaints, reviews, vendor order maps linked to those)');

        if ($this->option('dry-run')) {
            $this->info('Dry run only — nothing deleted.');

            return self::SUCCESS;
        }

        if ($visitCount === 0 && $orderCount === 0) {
            $this->info('Already clean — no visits or orders.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('DELETE all visits and orders now?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            // Break order_item FK on visits before wiping orders/items.
            if (Schema::hasColumn('visits', 'order_item_id')) {
                Visit::query()->whereNotNull('order_item_id')->update(['order_item_id' => null]);
            }
            if (Schema::hasColumn('visits', 'order_id')) {
                Visit::query()->whereNotNull('order_id')->update(['order_id' => null]);
            }

            if (Schema::hasTable('visit_supervisor_declines')) {
                VisitSupervisorDecline::query()->delete();
            }
            if (Schema::hasTable('visit_photos')) {
                VisitPhoto::query()->delete();
            }
            if (Schema::hasTable('visit_offers')) {
                VisitOffer::query()->delete();
            }
            if (Schema::hasTable('reports')) {
                Report::query()->delete();
            }
            if (Schema::hasTable('complaints')) {
                Complaint::query()->delete();
            }

            Visit::query()->delete();

            if (Schema::hasTable('reviews')) {
                Review::query()->delete();
            }
            if (Schema::hasTable('vendor_order_mappings')) {
                VendorOrderMapping::query()->delete();
            }
            if (Schema::hasTable('wallet_credits') && Schema::hasColumn('wallet_credits', 'order_id')) {
                DB::table('wallet_credits')->whereNotNull('order_id')->update(['order_id' => null]);
            }
            if (Schema::hasTable('order_items')) {
                OrderItem::query()->delete();
            }

            Order::query()->delete();
        });

        $this->info('Done.');
        $this->line('Remaining visits: '.Visit::query()->count());
        $this->line('Remaining orders: '.Order::query()->count());

        return self::SUCCESS;
    }
}
