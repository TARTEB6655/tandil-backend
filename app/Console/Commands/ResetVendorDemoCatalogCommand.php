<?php

namespace App\Console\Commands;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Service;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Replace vendor marketplace catalog only.
 * Never deletes admin/platform products (vendor_id null).
 */
class ResetVendorDemoCatalogCommand extends Command
{
    protected $signature = 'vendor:reset-demo-catalog
                            {--vendor= : Limit to one vendor id}
                            {--force : Required in production / non-interactive}';

    protected $description = 'Delete ALL vendor-owned products, then seed 5 simple + 5 service products per approved vendor (admin/client catalog untouched)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Production requires --force.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Delete all vendor products and seed 5 simple + 5 service per approved vendor?', true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $vendorsQuery = Vendor::query()->with('profile')->orderBy('id');
        if ($this->option('vendor')) {
            $vendorsQuery->where('id', (int) $this->option('vendor'));
        } else {
            $vendorsQuery->where('status', VendorStatus::Approved->value);
        }

        $vendors = $vendorsQuery->get();
        if ($vendors->isEmpty()) {
            $this->error('No matching vendors found.');

            return self::FAILURE;
        }

        $category = Category::query()->where('is_active', true)->orderBy('id')->first()
            ?? Category::query()->orderBy('id')->first();

        if (! $category) {
            $category = Category::create([
                'name' => 'General',
                'slug' => 'general-'.Str::lower(Str::random(4)),
                'is_active' => true,
                'shipping_cost' => 0,
                'tax_percentage' => 0,
            ]);
        }

        $services = $this->ensurePlatformServices(5);

        $simpleNames = [
            'Fresh Seasonal Fruits Box',
            'Premium Meat Pack',
            'Organic Vegetable Basket',
            'Dairy Essentials Kit',
            'Grocery Staples Bundle',
        ];

        $serviceNames = [
            'AC Deep Cleaning',
            'Plumbing Repair Visit',
            'Electrical Safety Check',
            'Home Appliance Service',
            'Pest Control Treatment',
        ];

        DB::transaction(function () use ($vendors, $category, $services, $simpleNames, $serviceNames) {
            $this->purgeVendorCatalog($vendors->pluck('id')->all());

            foreach ($vendors as $vendor) {
                $label = $vendor->profile->business_name ?? ('Vendor #'.$vendor->id);
                $this->info("Seeding vendor {$vendor->id} ({$label})");

                foreach ($simpleNames as $i => $name) {
                    $this->createVendorListing(
                        vendor: $vendor,
                        category: $category,
                        name: $name,
                        type: 'product',
                        price: 25 + (($i + 1) * 15),
                        stock: 20 + $i,
                        serviceId: null,
                        jobDuration: null
                    );
                }

                foreach ($serviceNames as $i => $name) {
                    $service = $services[$i] ?? $services->first();
                    $this->createVendorListing(
                        vendor: $vendor,
                        category: $category,
                        name: $name,
                        type: 'service',
                        price: 80 + (($i + 1) * 20),
                        stock: 99,
                        serviceId: (int) $service->id,
                        jobDuration: (60 + ($i * 15)).' min'
                    );
                }
            }
        });

        $adminLeft = Product::query()->whereNull('vendor_id')->count();
        $vendorOwned = Product::query()->whereNotNull('vendor_id')->count();
        $this->newLine();
        $this->info("Done. vendor_owned_products={$vendorOwned} admin/client_products_untouched={$adminLeft}");

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $vendorIds
     */
    private function purgeVendorCatalog(array $vendorIds): void
    {
        $productIds = Product::query()
            ->whereNotNull('vendor_id')
            ->when($vendorIds !== [], fn ($q) => $q->whereIn('vendor_id', $vendorIds))
            ->pluck('id');

        $vpIds = VendorProduct::withTrashed()
            ->where(function ($q) use ($vendorIds, $productIds) {
                $q->whereIn('vendor_id', $vendorIds);
                if ($productIds->isNotEmpty()) {
                    $q->orWhereIn('product_id', $productIds);
                }
            })
            ->pluck('id')
            ->unique()
            ->values();

        $this->warn("Purging vendor_products={$vpIds->count()} vendor_products_rows products={$productIds->count()} (admin catalog kept)");

        if ($vpIds->isNotEmpty()) {
            VendorProductPrice::query()->whereIn('vendor_product_id', $vpIds)->delete();
            VendorInventory::query()->whereIn('vendor_product_id', $vpIds)->delete();
            VendorProduct::withTrashed()->whereIn('id', $vpIds)->forceDelete();
        }

        if ($productIds->isEmpty()) {
            return;
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

    private function createVendorListing(
        Vendor $vendor,
        Category $category,
        string $name,
        string $type,
        float $price,
        int $stock,
        ?int $serviceId,
        ?string $jobDuration
    ): void {
        $suffix = '-v'.$vendor->id.'-'.Str::lower(Str::random(4));
        $handle = Str::slug($name).$suffix;

        $payload = [
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => $name,
            'handle' => $handle,
            'description' => $type === 'service'
                ? $name.' — service job via supervisor/technician.'
                : $name.' — simple product fulfilled by vendor.',
            'price' => $price,
            'stock' => $stock,
            'status' => 'active',
            'type' => $type,
            'product_type' => 'simple',
        ];
        if ($jobDuration !== null) {
            $payload['job_duration'] = $jobDuration;
        }

        $product = Product::create($payload);

        if ($serviceId !== null && method_exists($product, 'services')) {
            $product->services()->sync([$serviceId]);
        }

        $vp = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        VendorProductPrice::create([
            'vendor_product_id' => $vp->id,
            'price' => $price,
            'effective_from' => now(),
        ]);

        VendorInventory::create([
            'vendor_product_id' => $vp->id,
            'quantity' => $stock,
            'low_stock_threshold' => $type === 'service' ? 1 : 3,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Service>
     */
    private function ensurePlatformServices(int $need)
    {
        $services = Service::query()->orderBy('id')->limit($need)->get();
        $i = 1;
        while ($services->count() < $need) {
            $services->push(Service::create([
                'name' => 'Demo Service '.$i,
                'slug' => 'demo-service-'.$i.'-'.Str::lower(Str::random(4)),
                'description' => 'Platform service for vendor demo catalog',
                'is_active' => true,
            ]));
            $i++;
            $services = Service::query()->orderBy('id')->limit($need)->get();
        }

        return $services->values();
    }
}
