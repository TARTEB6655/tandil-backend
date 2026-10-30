<?php

namespace Tests\Feature\Admin;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorProductAreaPricingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private Vendor $vendor;

    private Product $serviceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->adminToken = $admin->createToken('test', ['admin'])->plainTextToken;

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendorUser->assignRole('vendor');
        $this->vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $this->vendor->id,
            'business_name' => 'Demo Nursery',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::factory()->create();
        $this->serviceProduct = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $this->vendor->id,
            'type' => 'service',
            'name' => 'Landscaping',
            'description' => 'Garden design & maintenance',
            'price' => 300,
            'status' => 'active',
        ]);
    }

    public function test_admin_lists_vendors_for_area_pricing(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/vendors/area-pricing')
            ->assertOk()
            ->assertJsonPath('data.items.0.vendor_id', $this->vendor->id)
            ->assertJsonPath(
                'data.items.0.products_area_pricing_endpoint',
                '/api/admin/vendors/'.$this->vendor->id.'/products/area-pricing'
            );
    }

    public function test_admin_per_service_area_pricing_end_to_end(): void
    {
        $base = '/api/admin/vendors/'.$this->vendor->id;

        $this->withToken($this->adminToken)
            ->getJson($base.'/products/area-pricing')
            ->assertOk()
            ->assertJsonPath('data.vendor_id', $this->vendor->id)
            ->assertJsonPath('data.items.0.product_id', $this->serviceProduct->id)
            ->assertJsonPath(
                'data.items.0.detail_endpoint',
                $base.'/products/'.$this->serviceProduct->id.'/area-pricing'
            );

        $this->withToken($this->adminToken)
            ->getJson($base.'/products/'.$this->serviceProduct->id.'/area-pricing')
            ->assertOk()
            ->assertJsonPath('data.screen_title', 'Area pricing for this service')
            ->assertJsonPath('data.vendor.vendor_id', $this->vendor->id)
            ->assertJsonPath('data.show_on_client_products_services', false);

        $this->withToken($this->adminToken)
            ->postJson($base.'/products/'.$this->serviceProduct->id.'/area-pricing', [
                'show_on_client_products_services' => true,
                'enable_fixed_price' => true,
                'enable_per_m2' => true,
                'require_area_before_checkout' => false,
                'fixed_price' => 600,
                'per_m2_price' => 20,
                'price_includes' => array_merge(ServiceAreaPricing::emptyIncludes(), [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('data.area_pricing_configured', true)
            ->assertJsonPath('data.fixed_price', 600)
            ->assertJsonPath('data.price_per_m2', 20)
            ->assertJsonPath('data.show_on_client_products_services', true);

        $this->withToken($this->adminToken)
            ->postJson($base.'/products/'.$this->serviceProduct->id.'/area-pricing', [
                'show_on_client_products_services' => false,
                'enable_fixed_price' => false,
                'enable_per_m2' => true,
                'require_area_before_checkout' => false,
                'fixed_price' => 0,
                'per_m2_price' => 20,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.show_on_client_products_services', false);

        $this->getJson('/api/shop/products/'.$this->serviceProduct->id)
            ->assertOk()
            ->assertJsonPath('data.show_area_pricing_on_client', false);

        $this->withToken($this->adminToken)
            ->postJson($base.'/products/'.$this->serviceProduct->id.'/area-pricing', [
                'show_on_client_products_services' => true,
                'enable_fixed_price' => false,
                'enable_per_m2' => true,
                'require_area_before_checkout' => false,
                'fixed_price' => 0,
                'per_m2_price' => 35,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk();

        $this->getJson('/api/shop/products/'.$this->serviceProduct->id)
            ->assertOk()
            ->assertJsonPath('data.show_area_pricing_on_client', true)
            ->assertJsonPath('data.price_per_m2', 35)
            ->assertJsonPath('data.pricing_type', 'per_m2');
    }

    public function test_admin_cannot_manage_other_vendors_product(): void
    {
        $otherVendor = Vendor::create([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/vendors/'.$this->vendor->id.'/products/'.$this->serviceProduct->id.'/area-pricing')
            ->assertOk();

        Product::query()->whereKey($this->serviceProduct->id)->update(['vendor_id' => $otherVendor->id]);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/vendors/'.$this->vendor->id.'/products/'.$this->serviceProduct->id.'/area-pricing')
            ->assertNotFound();
    }
}
