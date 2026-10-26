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
            ->assertJsonPath('data.applies_to', 'vendor_service_products');

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
        $this->assertTrue($fields['area_pricing_hidden_on_client'] ?? false);

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
            ->assertJsonPath('data.applies_to', 'vendor_service_products');

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
