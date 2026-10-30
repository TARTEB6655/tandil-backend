<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\ProductAreaPricing;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorProductAreaPricingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Vendor $vendor;

    private Product $serviceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $user->assignRole('vendor');

        $this->vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $this->vendor->id,
            'business_name' => 'Demo Vendor',
            'owner_name' => 'Owner',
            'email' => $user->email,
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

        $this->token = $user->createToken('test', ['vendor'])->plainTextToken;
    }

    public function test_vendor_lists_service_products_for_area_pricing(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/vendor/products/area-pricing')
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', $this->serviceProduct->id)
            ->assertJsonPath('data.items.0.name', 'Landscaping')
            ->assertJsonPath('data.items.0.catalog_price', 300)
            ->assertJsonPath('data.items.0.area_pricing_configured', false)
            ->assertJsonPath('data.items.0.detail_endpoint', '/api/vendor/products/'.$this->serviceProduct->id.'/area-pricing');
    }

    public function test_vendor_saves_per_product_area_pricing_and_shop_reflects_rate(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/vendor/products/'.$this->serviceProduct->id.'/area-pricing')
            ->assertOk()
            ->assertJsonPath('data.screen_title', 'Area pricing for this service')
            ->assertJsonPath('data.product_name', 'Landscaping');

        $this->withToken($this->token)
            ->postJson('/api/vendor/products/'.$this->serviceProduct->id.'/area-pricing', [
                'show_on_client_products_services' => true,
                'enable_fixed_price' => false,
                'enable_per_m2' => true,
                'require_area_before_checkout' => false,
                'fixed_price' => 0,
                'per_m2_price' => 20,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.area_pricing_configured', true)
            ->assertJsonPath('data.price_per_m2', 20)
            ->assertJsonPath('data.show_on_client_products_services', true);

        $this->assertTrue(ProductAreaPricing::isConfigured($this->serviceProduct->id));

        $this->withToken($this->token)
            ->getJson('/api/vendor/products/area-pricing')
            ->assertOk()
            ->assertJsonPath('data.items.0.price_per_m2', 20)
            ->assertJsonPath('data.items.0.price_per_m2_label', 'AED 20 / m²');

        $this->getJson('/api/shop/products/'.$this->serviceProduct->id)
            ->assertOk()
            ->assertJsonPath('data.show_area_pricing_on_client', true)
            ->assertJsonPath('data.price_per_m2', 20)
            ->assertJsonPath('data.pricing_type', 'per_m2');
    }

    public function test_vendor_product_type_catalog_line_can_save_per_m2_without_enable_toggle(): void
    {
        $catalogProduct = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'vendor_id' => $this->vendor->id,
            'type' => 'product',
            'name' => 'Hardscape',
            'price' => 600,
            'status' => 'active',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/vendor/products/'.$catalogProduct->id.'/area-pricing', [
                'show_on_client_products_services' => true,
                'enable_fixed_price' => false,
                'enable_per_m2' => false,
                'fixed_price' => 0,
                'per_m2_price' => 55,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.price_per_m2', 55)
            ->assertJsonPath('data.enable_per_m2', true);

        $this->getJson('/api/shop/products/'.$catalogProduct->id)
            ->assertOk()
            ->assertJsonPath('data.show_area_pricing_on_client', true)
            ->assertJsonPath('data.price_per_m2', 55);
    }

    public function test_require_area_before_checkout_validates_cart(): void
    {
        ProductAreaPricing::save(
            $this->serviceProduct,
            false,
            true,
            0,
            25,
            ServiceAreaPricing::emptyIncludes(),
            true,
            true
        );

        $client = User::factory()->create(['role' => 'client']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'area_pricing_mode' => 'per_m2',
            ])
            ->assertStatus(422);
    }
}
