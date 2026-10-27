<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorServicePricingSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Vendor $vendor;

    private Product $serviceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        ServiceTreePricing::clearSchemaCache();

        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('vendor');
        }

        $this->vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $this->vendor->id,
            'business_name' => 'Pricing Vendor',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        Service::factory()->create(['vendor_id' => $this->vendor->id, 'name' => 'Paving']);
        $category = Category::factory()->create();
        $this->serviceProduct = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $this->vendor->id,
            'type' => 'service',
            'name' => 'Outdoor Service',
            'price' => 100,
            'status' => 'active',
        ]);

        $this->token = $user->createToken('test', ['vendor'])->plainTextToken;
    }

    public function test_vendor_service_pricing_get_and_update(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/vendor/settings/service-pricing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.applies_to', 'vendor_catalog_products');

        $this->withToken($this->token)
            ->postJson('/api/vendor/settings/service-pricing', [
                'pricing_type' => 'per_m2',
                'price' => 7,
                'show_on_client_products_services' => true,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => true,
                    'delivery' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.price', 7)
            ->assertJsonPath('data.price_includes.labor', true)
            ->assertJsonPath('data.synced_services', 1)
            ->assertJsonPath('data.synced_products', 1)
            ->assertJsonPath('data.currency', 'AED')
            ->assertJsonPath('data.example_calculation.total', 700);
    }

    public function test_vendor_service_pricing_get_includes_mobile_ui_fields(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/vendor/settings/service-pricing')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'vendor_id',
                    'applies_to',
                    'pricing_type',
                    'pricing_type_options',
                    'price',
                    'currency',
                    'price_input_label',
                    'price_input_suffix',
                    'price_includes',
                    'price_includes_options',
                    'example_calculation' => ['title', 'total'],
                ],
            ])
            ->assertJsonPath('data.pricing_type_options.0.value', 'fixed')
            ->assertJsonPath('data.pricing_type_options.1.value', 'per_m2');
    }

    public function test_vendor_per_m2_rate_applies_to_checkout_not_global_admin_rate(): void
    {
        ServiceAreaPricing::saveVendor(
            $this->vendor->id,
            ServiceAreaPricing::TYPE_PER_M2,
            7,
            ServiceAreaPricing::emptyIncludes()
        );

        $this->serviceProduct->refresh();
        $fields = ServiceAreaPricing::productApiFields($this->serviceProduct);
        $this->assertSame(7.0, (float) $fields['price_per_m2']);
        $this->assertSame('per_m2', $fields['pricing_type']);
        $this->assertSame(
            700.0,
            ServiceAreaPricing::lineTotal($this->serviceProduct, 7, 1, 100)
        );
        $client = User::factory()->create(['role' => 'client']);
        $this->assignClientRole($client);

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/buy-now/summary', [
            'is_buy_now' => true,
            'product_id' => $this->serviceProduct->id,
            'quantity' => 1,
            'required_area' => 100,
        ])->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 700)
            ->assertJsonPath('data.order_summary.items.0.line_total', 700)
            ->assertJsonPath('data.order_summary.items.0.pricing_type', 'per_m2');
    }

    public function test_vendor_area_pricing_hidden_on_client_when_toggle_off(): void
    {
        ServiceAreaPricing::saveVendor(
            $this->vendor->id,
            ServiceAreaPricing::TYPE_PER_M2,
            7,
            ServiceAreaPricing::emptyIncludes(),
            false
        );

        $this->serviceProduct->refresh();
        $fields = ServiceAreaPricing::productApiFields($this->serviceProduct);
        $this->assertSame('fixed', $fields['pricing_type']);
        $this->assertNull($fields['price_per_m2']);
        $this->assertFalse($fields['show_area_pricing_on_client']);
        $this->assertArrayNotHasKey('area_pricing_hidden_on_client', $fields);

        $this->withToken($this->token)
            ->getJson('/api/vendor/settings/service-pricing')
            ->assertOk()
            ->assertJsonPath('data.show_on_client_products_services', false);
    }

    public function test_vendor_tree_palm_pricing_get_and_update(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/vendor/settings/tree-palm-pricing')
            ->assertOk()
            ->assertJsonPath('data.applies_to', 'vendor_catalog_products');

        $this->withToken($this->token)
            ->postJson('/api/vendor/settings/tree-palm-pricing', [
                'show_tree_options' => true,
                'price_per_tree' => 50,
                'price_per_palm_tree' => 50,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => true,
                    'delivery' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.show_tree_options', true)
            ->assertJsonPath('data.price_per_tree', 50)
            ->assertJsonPath('data.price_per_palm_tree', 50)
            ->assertJsonPath('data.currency', 'AED')
            ->assertJsonPath('data.toggle_label', 'Show on client products & services')
            ->assertJsonPath('data.show_on_client_products_services', true)
            ->assertJsonMissingPath('data.pricing_type');

        $fields = ServiceAreaPricing::productApiFields($this->serviceProduct->fresh());
        $this->assertTrue($fields['show_tree_options']);
        $this->assertSame(50.0, (float) $fields['tree_pricing']['trees']['unit_price']);
        $this->assertSame(50.0, (float) $fields['tree_pricing']['palm_trees']['unit_price']);
    }

    public function test_vendor_simple_product_type_shows_per_m2_and_tree_on_client_fields(): void
    {
        $category = Category::factory()->create();
        $simpleProduct = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $this->vendor->id,
            'type' => 'product',
            'product_type' => 'simple',
            'name' => 'Vendor Simple SKU',
            'price' => 200,
            'status' => 'active',
        ]);

        ServiceAreaPricing::saveVendor(
            $this->vendor->id,
            ServiceAreaPricing::TYPE_PER_M2,
            12,
            ServiceAreaPricing::emptyIncludes(),
            true
        );
        ServiceTreePricing::saveVendor($this->vendor->id, true, 30, 40);

        $fields = ServiceAreaPricing::productApiFields($simpleProduct->fresh());
        $this->assertTrue($fields['show_area_pricing_on_client']);
        $this->assertArrayNotHasKey('area_pricing_hidden_on_client', $fields);
        $this->assertSame('per_m2', $fields['pricing_type']);
        $this->assertSame(12.0, (float) $fields['price_per_m2']);
        $this->assertTrue($fields['show_tree_options']);
        $this->assertSame(30.0, (float) $fields['tree_pricing']['trees']['unit_price']);
    }

    public function test_vendor_tree_palm_buy_now_summary_includes_addons(): void
    {
        ServiceTreePricing::saveVendor($this->vendor->id, true, 50, 40);

        $resolved = ServiceTreePricing::resolveForCheckout($this->serviceProduct, 2, 1);
        $this->assertSame(2, $resolved['tree_quantity']);
        $this->assertSame(1, $resolved['palm_tree_quantity']);

        $client = User::factory()->create(['role' => 'client']);
        $this->assignClientRole($client);

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/buy-now/summary', [
            'is_buy_now' => true,
            'product_id' => $this->serviceProduct->id,
            'quantity' => 1,
            'tree_quantity' => 2,
            'palm_tree_quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 240)
            ->assertJsonPath('data.order_summary.items.0.line_total', 100)
            ->assertJsonPath('data.order_summary.items.0.tree_quantity', 2)
            ->assertJsonPath('data.order_summary.items.0.palm_tree_quantity', 1)
            ->assertJsonPath('data.order_summary.items.1.line_kind', 'tree_addon')
            ->assertJsonPath('data.order_summary.items.2.line_kind', 'palm_addon');
    }

    public function test_vendor_service_pricing_get_returns_both_rates_when_multiselect(): void
    {
        ServiceAreaPricing::saveVendorMultiselect(
            $this->vendor->id,
            true,
            true,
            300,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );

        $this->withToken($this->token)
            ->getJson('/api/vendor/settings/service-pricing')
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'multi')
            ->assertJsonPath('data.fixed_price', 300)
            ->assertJsonPath('data.per_m2_price', 70)
            ->assertJsonPath('data.price_per_m2', 70)
            ->assertJsonPath('data.area_pricing_settings.fixed.amount', 300)
            ->assertJsonPath('data.area_pricing_settings.per_m2.rate', 70)
            ->assertJsonPath('data.price', null);
    }

    public function test_vendor_multiselect_service_pricing_save_and_product_detail(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/vendor/settings/service-pricing', [
                'enable_fixed_price' => true,
                'enable_per_m2' => true,
                'fixed_price' => 23,
                'per_m2_price' => 70,
                'show_on_client_products_services' => true,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.enable_fixed_price', true)
            ->assertJsonPath('data.enable_per_m2', true)
            ->assertJsonPath('data.fixed_price', 23)
            ->assertJsonPath('data.per_m2_price', 70)
            ->assertJsonPath('data.pricing_type', 'multi');

        $fields = ServiceAreaPricing::productApiFields($this->serviceProduct->fresh());
        $this->assertFalse($fields['area_pricing_mode_required']);
        $this->assertSame('per_m2', $fields['default_area_pricing_mode']);
        $this->assertSame('multi', $fields['pricing_type']);
        $this->assertSame(70.0, (float) $fields['price_per_m2']);
        $this->assertNull($fields['checkout_price']);
        $this->assertSame(100.0, (float) $fields['price']);
        $this->assertSame(123.0, (float) $fields['area_pricing_options']['fixed']['checkout_price']);
        $this->assertSame(70.0, (float) $fields['area_pricing_options']['per_m2']['price_per_m2']);
        $preview = $fields['customer_preview'];
        $this->assertSame('multi', $preview['pricing_type']);
        $this->assertTrue($preview['breakdown']['do_not_add_lines']);
        $this->assertSame(23.0, (float) $preview['breakdown']['fixed_price']);
        $this->assertSame(70.0, (float) $preview['breakdown']['price_per_m2']);
        $this->assertArrayNotHasKey('total', $preview['breakdown']);
        $this->assertSame(123.0, (float) $preview['when_fixed_selected']['checkout_total']);
        $this->assertArrayNotHasKey('total', $preview['when_fixed_selected']['breakdown']);
        $this->assertArrayNotHasKey('formula', $preview['when_fixed_selected']['breakdown']);
        $this->assertSame(7000.0, (float) $preview['when_per_m2_selected']['example']['total']);
        $this->assertSame('AED 23', $fields['area_pricing_options']['fixed']['price_label']);
        $this->assertSame('AED 70 / m²', $fields['area_pricing_options']['per_m2']['price_label']);
    }

    public function test_legacy_fixed_save_preserves_existing_per_m2_when_both_enabled(): void
    {
        ServiceAreaPricing::saveVendorMultiselect(
            $this->vendor->id,
            true,
            true,
            300,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );

        $this->withToken($this->token)
            ->postJson('/api/vendor/settings/service-pricing', [
                'pricing_type' => 'fixed',
                'price' => 350,
                'show_on_client_products_services' => true,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.fixed_price', 350)
            ->assertJsonPath('data.per_m2_price', 70)
            ->assertJsonPath('data.enable_fixed_price', true)
            ->assertJsonPath('data.enable_per_m2', true);

        $fields = ServiceAreaPricing::productApiFields($this->serviceProduct->fresh());
        $this->assertSame(70.0, (float) $fields['price_per_m2']);
        $this->assertSame(350.0, (float) $fields['fixed_price_addon']);
    }

    public function test_vendor_multiselect_checkout_requires_mode_and_totals(): void
    {
        ServiceAreaPricing::saveVendorMultiselect(
            $this->vendor->id,
            true,
            true,
            23,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );

        $client = User::factory()->create(['role' => 'client']);
        $this->assignClientRole($client);

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $this->serviceProduct->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $this->serviceProduct->id,
            'quantity' => 1,
            'required_area' => 10,
        ])->assertSuccessful();

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $this->serviceProduct->id,
            'quantity' => 1,
            'area_pricing_mode' => 'per_m2',
            'required_area' => 10,
        ])->assertSuccessful();

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $this->serviceProduct->id,
            'quantity' => 1,
            'area_pricing_mode' => 'fixed',
        ])->assertSuccessful();

        $fixedTotal = ServiceAreaPricing::lineTotal(
            $this->serviceProduct,
            ServiceAreaPricing::effectiveUnitPrice($this->serviceProduct, 100, 'fixed'),
            1,
            null,
            null,
            null,
            null,
            null,
            'fixed'
        );
        $this->assertSame(123.0, $fixedTotal);
    }

    public function test_multiselect_buy_now_catalog_ignores_stale_cart_area(): void
    {
        ServiceAreaPricing::saveVendorMultiselect(
            $this->vendor->id,
            true,
            true,
            23,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );

        $client = User::factory()->create(['role' => 'client']);
        $this->assignClientRole($client);

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $this->serviceProduct->id,
            'quantity' => 1,
            'area_pricing_mode' => 'per_m2',
            'required_area' => 10,
        ])->assertSuccessful();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 100);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'items' => [
                    ['product_id' => $this->serviceProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 100);
    }

    private function assignClientRole(User $client): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
                if (method_exists($client, 'assignRole')) {
                    $client->assignRole('client');
                }
            }
        } catch (\Throwable $e) {
            //
        }
    }
}
