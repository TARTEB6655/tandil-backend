<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use App\Models\VendorDocument;
use App\Models\VendorInventory;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use App\Models\VendorProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeVendors extends Command
{
    /**
     * Examples:
     *   php artisan vendors:purge --test                 # delete vendor1/2/3@test.com
     *   php artisan vendors:purge --email=a@b.com        # delete a specific vendor (repeatable)
     *   php artisan vendors:purge --all                  # delete EVERY vendor (asks to confirm)
     *   php artisan vendors:purge --all --force          # delete EVERY vendor without prompt
     */
    protected $signature = 'vendors:purge
        {--email=* : Vendor account email(s) to delete}
        {--test : Delete the seeded test vendors (vendor1/2/3@test.com)}
        {--all : Delete ALL vendor accounts and their marketplace data}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently delete vendor accounts and all their marketplace data (profile, products, inventory, prices, documents, approval logs, order mappings, and seeded orders).';

    public function handle(): int
    {
        $emails = (array) $this->option('email');

        if ($this->option('test')) {
            $emails = array_merge($emails, ['vendor1@test.com', 'vendor2@test.com', 'vendor3@test.com']);
        }

        if ($this->option('all')) {
            $emails = User::where('role', 'vendor')->pluck('email')->all();
        }

        $emails = array_values(array_unique(array_filter($emails)));

        if ($emails === []) {
            $this->warn('Nothing to do. Pass --test, --all, or --email=someone@example.com');
            return self::INVALID;
        }

        $users = User::whereIn('email', $emails)->get();

        if ($users->isEmpty()) {
            $this->info('No matching vendor users found. Database is already clean for: ' . implode(', ', $emails));
            return self::SUCCESS;
        }

        $this->warn('The following vendor accounts (and all their data) will be PERMANENTLY deleted:');
        foreach ($users as $u) {
            $this->line("  - {$u->email} (user #{$u->id})");
        }

        if (! $this->option('force') && ! $this->confirm('Proceed?', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $deleted = 0;

        DB::transaction(function () use ($users, &$deleted) {
            foreach ($users as $user) {
                $vendor = Vendor::where('user_id', $user->id)->first();

                if ($vendor) {
                    $vendorProductIds = VendorProduct::where('vendor_id', $vendor->id)->pluck('id');

                    VendorProductPrice::whereIn('vendor_product_id', $vendorProductIds)->delete();
                    VendorInventory::whereIn('vendor_product_id', $vendorProductIds)->delete();
                    VendorOrderMapping::where('vendor_id', $vendor->id)->delete();
                    VendorProduct::where('vendor_id', $vendor->id)->delete();
                    VendorDocument::where('vendor_id', $vendor->id)->delete();
                    VendorApprovalLog::where('vendor_id', $vendor->id)->delete();
                    VendorProfile::where('vendor_id', $vendor->id)->delete();

                    $productIds = Product::where('vendor_id', $vendor->id)->pluck('id');
                    if ($productIds->isNotEmpty()) {
                        $orderIds = OrderItem::whereIn('product_id', $productIds)->pluck('order_id')->unique();
                        OrderItem::whereIn('product_id', $productIds)->delete();
                        foreach ($orderIds as $orderId) {
                            if (OrderItem::where('order_id', $orderId)->count() === 0) {
                                Order::where('id', $orderId)->delete();
                            }
                        }
                        Product::whereIn('id', $productIds)->delete();
                    }

                    $vendor->delete();
                }

                $user->forceDelete();
                $deleted++;
            }
        });

        $this->info("Done. Removed {$deleted} vendor account(s).");
        $this->info('Remaining vendor-role users: ' . User::where('role', 'vendor')->count());

        return self::SUCCESS;
    }
}
