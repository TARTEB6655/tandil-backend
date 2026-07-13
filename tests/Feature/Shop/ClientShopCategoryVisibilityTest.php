<?php

namespace Tests\Feature\Shop;

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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientShopCategoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        }
    }

    public function test_client_category_excludes_unapproved_vendor_products(): void
    {
        $category = Category::create([
            'name' => 'Fruits',
            'slug' => 'fruits',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $adminProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Admin Apple',
            'price' => 10,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::UnderReview->value,
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Pending Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $vendorProductModel = Product::create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'name' => 'Vendor Mango',
            'price' => 20,
            'status' => 'active',
            'sort_order' => 2,
        ]);

        $vendorProduct = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $vendorProductModel->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        VendorProductPrice::create([
            'vendor_product_id' => $vendorProduct->id,
            'price' => 20,
            'effective_from' => now(),
        ]);

        VendorInventory::create([
            'vendor_product_id' => $vendorProduct->id,
            'quantity' => 5,
        ]);

        $response = $this->getJson("/api/shop/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.products.0.name', 'Admin Apple')
            ->assertJsonCount(1, 'data.products');

        $names = collect($response->json('data.products'))->pluck('name')->all();
        $this->assertContains('Admin Apple', $names);
        $this->assertNotContains('Vendor Mango', $names);
    }

    public function test_client_category_shows_approved_live_vendor_products(): void
    {
        $category = Category::create([
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Live Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $vendorProductModel = Product::create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'name' => 'Live Tomato',
            'price' => 15,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $vendorProduct = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $vendorProductModel->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        VendorProductPrice::create([
            'vendor_product_id' => $vendorProduct->id,
            'price' => 15,
            'effective_from' => now(),
        ]);

        VendorInventory::create([
            'vendor_product_id' => $vendorProduct->id,
            'quantity' => 8,
        ]);

        $this->getJson("/api/shop/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Live Tomato');
    }
}
