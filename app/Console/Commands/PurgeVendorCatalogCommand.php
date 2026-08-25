<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete vendor-owned marketplace products only.
 * Never touches admin/platform catalog (products.vendor_id IS NULL).
 */
class PurgeVendorCatalogCommand extends Command
{
    protected $signature = 'vendor:purge-catalog
                            {--vendor= : Limit to one vendor id}
                            {--demo-only : Only remove known demo/seed product names (safer)}
                            {--force : Required in production / non-interactive}';

    protected $description = 'Delete vendor-owned products (admin/client platform catalog untouched). Use --demo-only for seeded demo SKUs only.';

    /** Names used by vendor:reset-demo-catalog + compare-demo seeder. */
    private const DEMO_PRODUCT_NAMES = [
        'Fresh Seasonal Fruits Box',
        'Premium Meat Pack',
        'Organic Vegetable Basket',
        'Dairy Essentials Kit',
        'Grocery Staples Bundle',
        'AC Deep Cleaning',
        'Plumbing Repair Visit',
        'Electrical Safety Check',
        'Home Appliance Service',
        'Pest Control Treatment',
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Production requires --force.');

            return self::FAILURE;
        }

        $vendorId = $this->option('vendor') ? (int) $this->option('vendor') : null;
        $demoOnly = (bool) $this->option('demo-only');

        $productsQuery = Product::query()->whereNotNull('vendor_id');
        if ($vendorId) {
            $productsQuery->where('vendor_id', $vendorId);
        }
        if ($demoOnly) {
            $productsQuery->whereIn('name', self::DEMO_PRODUCT_NAMES);
        }

        $productIds = $productsQuery->pluck('id');
        $count = $productIds->count();

        if ($count === 0) {
            $this->info('No matching vendor products found.');

            return self::SUCCESS;
        }

        $adminLeft = Product::query()->whereNull('vendor_id')->count();
        $scope = $demoOnly ? 'DEMO-NAMED vendor products' : 'ALL vendor-owned products';
        $vendorLabel = $vendorId ? "vendor_id={$vendorId}" : 'all vendors';

        $this->warn("About to delete {$count} {$scope} ({$vendorLabel}).");
        $this->line("Admin/platform products that will be kept: {$adminLeft}");

        if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($productIds) {
            $this->purge($productIds->all());
        });

        $remainingVendor = Product::query()->whereNotNull('vendor_id')->count();
        $remainingAdmin = Product::query()->whereNull('vendor_id')->count();
        $this->info("Done. Deleted {$count}. Remaining vendor_owned={$remainingVendor} platform={$remainingAdmin}");

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $productIds
     */
    private function purge(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $vpIds = VendorProduct::withTrashed()
            ->whereIn('product_id', $productIds)
            ->pluck('id');

        if ($vpIds->isNotEmpty()) {
            VendorProductPrice::query()->whereIn('vendor_product_id', $vpIds)->delete();
            VendorInventory::query()->whereIn('vendor_product_id', $vpIds)->delete();
            VendorProduct::withTrashed()->whereIn('id', $vpIds)->forceDelete();
        }

        if (Schema::hasTable('product_service')) {
            DB::table('product_service')->whereIn('product_id', $productIds)->delete();
        }
        if (Schema::hasTable('product_images')) {
            ProductImage::query()->whereIn('product_id', $productIds)->delete();
        }
        if (Schema::hasTable('product_option_groups')) {
            $groupIds = DB::table('product_option_groups')->whereIn('product_id', $productIds)->pluck('id');
            if ($groupIds->isNotEmpty() && Schema::hasTable('product_options')) {
                DB::table('product_options')->whereIn('product_option_group_id', $groupIds)->delete();
            }
            DB::table('product_option_groups')->whereIn('product_id', $productIds)->delete();
        }
        if (Schema::hasTable('product_variants')) {
            DB::table('product_variants')->whereIn('product_id', $productIds)->delete();
        }

        Product::query()->whereIn('id', $productIds)->delete();
    }
}
