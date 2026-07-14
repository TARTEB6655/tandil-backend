<?php

namespace App\Console\Commands;

use App\Enums\VendorStatus;
use App\Models\Product;
use Database\Seeders\VendorCompareDemoSeeder;
use Illuminate\Console\Command;

class VendorCompareDemoStatusCommand extends Command
{
    protected $signature = 'vendor:compare-demo-status';

    protected $description = 'Show compare-vendors demo vendors, product IDs, visibility, and test URLs';

    public function handle(): int
    {
        $this->info('Compare vendors demo status');
        $this->newLine();

        $foundAny = false;

        foreach (VendorCompareDemoSeeder::VENDORS as $vendorData) {
            $product = Product::query()
                ->whereHas('vendorAccount', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('email', $vendorData['email'])))
                ->where('name', $vendorData['product_name'])
                ->with(['vendorProduct.vendor', 'category'])
                ->first();

            if (! $product) {
                $this->warn("Missing: {$vendorData['business_name']} — run seeder first.");
                $this->line('  php artisan db:seed --class=Database/Seeders/VendorCompareDemoSeeder --force');
                $this->newLine();

                continue;
            }

            $foundAny = true;
            $visible = Product::query()
                ->visibleInClientShop()
                ->where('products.id', $product->id)
                ->exists();

            $this->line("Vendor: {$vendorData['business_name']} ({$vendorData['email']})");
            $this->line("  Product ID: {$product->id} — {$product->name}");
            $this->line('  Category: '.($product->category?->name ?? 'none').' (ID: '.($product->category_id ?? 'null').')');
            $this->line('  Product status: '.$product->status);
            $this->line('  Vendor status: '.($product->vendorProduct?->vendor?->status ?? 'n/a'));
            $this->line('  Listing approval: '.($product->vendorProduct?->approval_status ?? 'n/a'));
            $this->line('  Shop visible: '.($visible ? 'YES' : 'NO'));
            $this->newLine();
        }

        if (! $foundAny) {
            $this->error('No demo products found. Seed demo data first.');

            return self::FAILURE;
        }

        $sample = Product::query()
            ->visibleInClientShop()
            ->whereHas('vendorAccount.user', fn ($q) => $q->where('email', VendorCompareDemoSeeder::VENDORS[0]['email']))
            ->first();

        if (! $sample) {
            $this->warn('Demo products exist but none are visible in client shop.');
            $this->line('Fix: approve vendors, set products active, approval_status=approved.');

            return self::FAILURE;
        }

        $base = rtrim(config('app.url'), '/');
        $this->info('Use this product_id in Postman: '.$sample->id);
        $this->line("  GET {$base}/api/shop/products/{$sample->id}");
        $this->line("  GET {$base}/api/shop/products/{$sample->id}/compare-vendors?sort_by=price");

        $compareCount = Product::query()
            ->visibleInClientShop()
            ->where('category_id', $sample->category_id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $sample->name))])
            ->whereHas('vendorProduct')
            ->count();

        $this->newLine();
        $this->line("Visible vendor products in same category: {$compareCount}");
        $this->line('compare_vendors.available = '.($compareCount >= 2 ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
