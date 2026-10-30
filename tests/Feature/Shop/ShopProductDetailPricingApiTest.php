<?php

namespace Tests\Feature\Shop;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopProductDetailPricingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_product_show_includes_client_pricing_and_trees_section(): void
    {
        ServiceTreePricing::clearSchemaCache();
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $user = User::factory()->create(['role' => 'vendor']);
        $user->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Vendor',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        ServiceAreaPricing::saveVendorMultiselect(
            $vendor->id,
            true,
            true,
            300,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );
        ServiceTreePricing::saveVendor($vendor->id, true, 50, 50);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'service',
            'name' => 'test service name',
            'price' => 300,
            'status' => 'active',
        ]);

        $this->getJson('/api/shop/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'test service name')
            ->assertJsonPath('data.show_on_client_products_services', true)
            ->assertJsonPath('data.show_area_pricing_on_client', true)
            ->assertJsonPath('data.show_tree_palm_on_client', true)
            ->assertJsonPath('data.pricing_type', 'multi')
            ->assertJsonPath('data.price_per_m2', 70)
            ->assertJsonPath('data.show_tree_options', true)
            ->assertJsonPath('data.tree_pricing.trees.unit_price', 50)
            ->assertJsonPath('data.trees_and_palm_trees.visible', true)
            ->assertJsonPath('data.trees_and_palm_trees.section_title', 'Trees & palm trees')
            ->assertJsonPath('data.trees_and_palm_trees.tree_pricing.palm_trees.unit_price', 50)
            ->assertJsonPath('data.client_pricing.visibility.show_on_client_products_services', true)
            ->assertJsonPath('data.client_pricing.trees_and_palm_trees.visible', true)
            ->assertJsonPath('data.client_pricing.area_pricing_options.per_m2.price_per_m2', 70);
    }
}
