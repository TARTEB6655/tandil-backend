<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurgeVendorCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_demo_only_keeps_real_vendor_and_platform_products(): void
    {
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $category = Category::create([
            'name' => 'Cat',
            'slug' => 'cat-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $platform = Product::create([
            'vendor_id' => null,
            'category_id' => $category->id,
            'name' => 'Platform Keep Me',
            'price' => 10,
            'status' => 'active',
            'type' => 'product',
        ]);

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Shop',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $demo = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Fresh Seasonal Fruits Box',
            'price' => 42,
            'status' => 'active',
            'type' => 'product',
        ]);
        $this->attachListing($vendor->id, $demo->id, 42);

        $real = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Real Vendor SKU',
            'price' => 99,
            'status' => 'active',
            'type' => 'product',
        ]);
        $this->attachListing($vendor->id, $real->id, 99);

        $this->artisan('vendor:purge-catalog', ['--demo-only' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('products', ['id' => $demo->id]);
        $this->assertDatabaseHas('products', ['id' => $real->id, 'name' => 'Real Vendor SKU']);
        $this->assertDatabaseHas('products', ['id' => $platform->id, 'name' => 'Platform Keep Me']);
    }

    private function attachListing(int $vendorId, int $productId, float $price): void
    {
        $vp = VendorProduct::create([
            'vendor_id' => $vendorId,
            'product_id' => $productId,
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
            'quantity' => 10,
            'low_stock_threshold' => 2,
        ]);
    }
}
