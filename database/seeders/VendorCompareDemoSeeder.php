<?php

namespace Database\Seeders;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use App\Models\VendorProfile;
use App\Services\Vendor\VendorApprovalService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class VendorCompareDemoSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@tandil.com';

    public const ADMIN_PASSWORD = 'password123';

    /** @var array<int, array<string, mixed>> */
    public const VENDORS = [
        [
            'email' => 'vendor1.fruits@tandil.com',
            'password' => 'Vendor123!',
            'business_name' => 'Green Valley Nursery',
            'owner_name' => 'Ahmed Hassan',
            'phone' => '501000001',
            'product_name' => 'Fresh Seasonal Fruits Box',
            'price' => 42.00,
            'compare_at_price' => 55.00,
            'stock' => 85,
            'estimated_arrival' => '2 day delivery',
            'delivery_radius' => 20,
        ],
        [
            'email' => 'vendor2.fruits@tandil.com',
            'password' => 'Vendor123!',
            'business_name' => 'Desert Bloom Supplies',
            'owner_name' => 'Sara Al Mansoori',
            'phone' => '501000002',
            'product_name' => 'Fresh Seasonal Fruits Box',
            'price' => 48.00,
            'compare_at_price' => null,
            'stock' => 40,
            'estimated_arrival' => '1 day delivery',
            'delivery_radius' => 8,
        ],
    ];

    public function run(): void
    {
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();
        if (! $admin) {
            $this->command?->warn('Admin user not found. Run AdminUserSeeder first.');

            return;
        }

        $category = Category::query()->updateOrCreate(
            ['slug' => 'fruits-compare-demo'],
            [
                'vendor_id' => null,
                'name' => 'Fruits',
                'description' => 'Fresh fruits for compare vendors demo',
                'is_active' => true,
                'sort_order' => Category::nextSortOrder(),
                'shipping_cost' => 0,
                'tax_percentage' => 5,
            ]
        );

        $approval = app(VendorApprovalService::class);
        $created = [];

        foreach (self::VENDORS as $index => $vendorData) {
            $user = User::query()->updateOrCreate(
                ['email' => $vendorData['email']],
                [
                    'name' => $vendorData['owner_name'],
                    'phone' => $vendorData['phone'],
                    'password' => $vendorData['password'],
                    'role' => 'vendor',
                    'status' => 'active',
                ]
            );
            $user->assignRole('vendor');

            $vendor = Vendor::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['status' => VendorStatus::UnderReview->value]
            );

            VendorProfile::query()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'business_name' => $vendorData['business_name'],
                    'owner_name' => $vendorData['owner_name'],
                    'email' => $vendorData['email'],
                    'phone' => $vendorData['phone'],
                    'delivery_radius' => $vendorData['delivery_radius'],
                    'operating_hours' => '08:00 - 20:00',
                ]
            );

            if ($vendor->status !== VendorStatus::Approved->value) {
                $approval->approve($vendor, $admin, 'Approved for compare vendors demo.');
                $vendor->refresh();
            }

            $product = Product::query()->updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'name' => $vendorData['product_name'],
                ],
                [
                    'category_id' => $category->id,
                    'price' => $vendorData['price'],
                    'compare_at_price' => $vendorData['compare_at_price'],
                    'stock' => $vendorData['stock'],
                    'status' => 'active',
                    'sort_order' => $index + 1,
                    'estimated_arrival' => $vendorData['estimated_arrival'],
                    'requires_shipping' => true,
                ]
            );

            $vendorProduct = VendorProduct::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'vendor_id' => $vendor->id,
                    'status' => 'active',
                    'approval_status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $admin->id,
                    'disabled_by_admin' => false,
                ]
            );

            VendorProductPrice::query()->updateOrCreate(
                [
                    'vendor_product_id' => $vendorProduct->id,
                    'price' => $vendorData['price'],
                ],
                [
                    'compare_at_price' => $vendorData['compare_at_price'],
                    'currency' => 'AED',
                    'effective_from' => now(),
                ]
            );

            VendorInventory::query()->updateOrCreate(
                ['vendor_product_id' => $vendorProduct->id],
                [
                    'quantity' => $vendorData['stock'],
                    'low_stock_threshold' => 5,
                ]
            );

            $created[] = [
                'vendor_id' => $vendor->id,
                'business_name' => $vendorData['business_name'],
                'email' => $vendorData['email'],
                'password' => $vendorData['password'],
                'product_id' => $product->id,
                'product_name' => $vendorData['product_name'],
                'price' => $vendorData['price'],
            ];
        }

        $sampleProductId = $created[0]['product_id'] ?? null;

        $this->command?->newLine();
        $this->command?->info('=== Compare Vendors Demo Data Ready ===');
        $this->command?->line('Admin login: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD);
        $this->command?->newLine();

        foreach ($created as $row) {
            $this->command?->line("Vendor: {$row['business_name']}");
            $this->command?->line("  Email: {$row['email']}");
            $this->command?->line("  Password: {$row['password']}");
            $this->command?->line("  Product ID: {$row['product_id']} — {$row['product_name']} (AED {$row['price']})");
            $this->command?->newLine();
        }

        $this->command?->line("Category: {$category->name} (ID: {$category->id})");
        $this->command?->line('Client APIs to test (no auth):');
        $this->command?->line("  GET /api/shop/products/{$sampleProductId}");
        $this->command?->line("  GET /api/shop/products/{$sampleProductId}/compare-vendors?sort_by=price");
        $this->command?->newLine();
    }
}
