<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceAreaPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = User::factory()->create(['role' => 'client']);
        $this->category = Category::create([
            'name' => 'Outdoor',
            'slug' => 'outdoor-'.uniqid(),
            'status' => 'active',
        ]);
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $this->admin->assignRole('admin');
            }
        } catch (\Throwable $e) {
            //
        }
    }

    public function test_global_service_pricing_settings_no_id(): void
    {
        $service = Service::create([
            'name' => 'Interlock',
            'slug' => 'interlock-'.uniqid(),
            'is_active' => true,
        ]);
        $product = Product::create([
            'name' => 'Paving Job',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 10,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'status' => 'active',
            'stock' => 999,
        ]);
        $service->products()->attach($product->id);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/settings/service-pricing')
            ->assertOk()
            ->assertJsonPath('data.applies_to', 'all_services')
            ->assertJsonPath('data.service_id', null)
            ->assertJsonPath('data.product_id', null);

        $this->actingAs($this->admin, 'sanctum')
            ->post('/api/admin/settings/service-pricing', [
                'pricing_type' => 'per_m2',
                'price' => '70',
                'price_includes' => [
                    'materials' => '1',
                    'installation' => '1',
                    'labor' => '1',
                    'transportation' => '0',
                    'delivery' => '0',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.price', 70)
            ->assertJsonPath('data.requires_area', true)
            ->assertJsonPath('data.example_calculation.total', 7000)
            ->assertJsonPath('data.synced_services', 1)
            ->assertJsonPath('data.synced_products', 1);

        $product->refresh();
        $service->refresh();
        $this->assertSame('per_m2', $product->pricing_type);
        // Catalog price must NOT be overwritten by global rate (store keeps product price).
        $this->assertEquals(10.0, (float) $product->price);
        $this->assertSame('per_m2', $service->pricing_type);

        $fields = ServiceAreaPricing::productApiFields($product->fresh());
        $this->assertSame(10.0, (float) $fields['price']);
        $this->assertSame(70.0, (float) $fields['price_per_m2']);
        $this->assertSame('AED 10', $fields['price_label']);
        $this->assertSame('AED 70 / m²', $fields['unit_rate_label']);
        $this->assertTrue($fields['requires_area']);
    }

    public function test_per_service_settings_do_not_overwrite_linked_product_catalog_price(): void
    {
        $service = Service::create([
            'name' => 'Interlock',
            'slug' => 'interlock-sync-'.uniqid(),
            'is_active' => true,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'price' => 7,
        ]);
        $product = Product::create([
            'name' => 'Paving Job',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 250,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'status' => 'active',
            'stock' => 999,
        ]);
        $service->products()->attach($product->id);

        $this->actingAs($this->admin, 'sanctum')
            ->post('/api/admin/services/'.$service->id.'/settings', [
                'pricing_type' => 'per_m2',
                'price' => '7',
                'price_includes' => [
                    'materials' => '1',
                    'installation' => '0',
                    'labor' => '0',
                    'transportation' => '0',
                    'delivery' => '0',
                ],
            ])
            ->assertOk();

        $product->refresh();
        $this->assertEquals(250.0, (float) $product->price);
        $this->assertSame('per_m2', $product->pricing_type);

        $fields = ServiceAreaPricing::productApiFields($product);
        $this->assertSame('AED 250', $fields['price_label']);
        $this->assertSame(250.0, (float) $fields['price']);
    }

    public function test_services_products_api_exposes_catalog_and_rate_fields(): void
    {
        ServiceAreaPricing::saveGlobal('per_m2', 7, ServiceAreaPricing::emptyIncludes());

        $service = Service::create([
            'name' => 'Construction',
            'slug' => 'construction-'.uniqid(),
            'is_active' => true,
        ]);
        $product = Product::create([
            'name' => 'Inspection Service',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 235,
            'status' => 'active',
            'stock' => 999,
        ]);
        $service->products()->attach($product->id);

        $this->getJson('/api/services/products?service_id='.$service->id)
            ->assertOk()
            ->assertJsonPath('data.data.0.price', 235)
            ->assertJsonPath('data.data.0.price_label', 'AED 235')
            ->assertJsonPath('data.data.0.catalog_price', 235)
            ->assertJsonPath('data.data.0.price_per_m2', 7)
            ->assertJsonPath('data.data.0.unit_rate_label', 'AED 7 / m²');
    }

    public function test_per_m2_rejected_for_non_service_products(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/products', [
            'name' => 'Garden Hose',
            'type' => 'product',
            'price' => 50,
            'pricing_type' => 'per_m2',
            'status' => 'active',
            'stock' => 10,
            'category_id' => $this->category->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['pricing_type']);
    }

    public function test_cart_uses_global_per_m2_for_all_services(): void
    {
        ServiceAreaPricing::saveGlobal('per_m2', 70, [
            'materials' => true,
            'installation' => true,
            'labor' => true,
            'transportation' => false,
            'delivery' => false,
        ]);

        $product = Product::create([
            'name' => 'Outdoor Paving',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 999, // ignored — global 70 wins
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'required_area' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.required_area', 100)
            ->assertJsonPath('data.line_total', 7000)
            ->assertJsonPath('data.current_price', 7000)
            ->assertJsonPath('data.display_price', 7000)
            ->assertJsonPath('data.unit_price', 70);

        // Mobile apps often send camelCase / short aliases.
        Cart::where('user_id', $this->client->id)->delete();
        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'requiredArea' => 7,
        ])->assertCreated()
            ->assertJsonPath('data.required_area', 7)
            ->assertJsonPath('data.line_total', 490); // 7 × 70

        // Area sent as "7 m²" string
        Cart::where('user_id', $this->client->id)->delete();
        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'area' => '7 m²',
        ])->assertCreated()
            ->assertJsonPath('data.required_area', 7)
            ->assertJsonPath('data.line_total', 490);

        // Some apps put area into quantity by mistake (not default 1)
        Cart::where('user_id', $this->client->id)->delete();
        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 7,
        ])->assertCreated()
            ->assertJsonPath('data.required_area', 7)
            ->assertJsonPath('data.line_total', 490);
    }

    public function test_global_fixed_service_uses_catalog_price_not_global_amount(): void
    {
        // Global Fixed "7" must NOT replace the product catalog price on cart.
        ServiceAreaPricing::saveGlobal('fixed', 7, [
            'materials' => true,
            'installation' => true,
            'labor' => true,
            'transportation' => false,
            'delivery' => false,
        ]);

        $product = Product::create([
            'name' => 'service product',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.price', 100)
            ->assertJsonPath('data.requires_area', false);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.requires_area', false)
            ->assertJsonPath('data.current_price', 100)
            ->assertJsonPath('data.display_price', 100)
            ->assertJsonPath('data.line_total', 100);

        // Stale unit_price=7 on cart row must still resolve to catalog 100.
        Cart::where('user_id', $this->client->id)->update(['unit_price' => 7]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.current_price', 100)
            ->assertJsonPath('data.items.0.line_total', 100)
            ->assertJsonPath('data.order_summary.subtotal', 100);
    }

    public function test_buy_now_order_summary_accepts_required_area(): void
    {
        ServiceAreaPricing::saveGlobal('per_m2', 7, ServiceAreaPricing::emptyIncludes());

        $product = Product::create([
            'name' => 'service product',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/order-summary?product_id='.$product->id.'&quantity=1&required_area=100')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 700)
            ->assertJsonPath('data.items.0.required_area', 100)
            ->assertJsonPath('data.items.0.line_total', 700);
    }

    public function test_buy_now_without_area_reuses_cart_required_area(): void
    {
        ServiceAreaPricing::saveGlobal('per_m2', 7, ServiceAreaPricing::emptyIncludes());

        $product = Product::create([
            'name' => 'service product',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'required_area' => 100,
        ])->assertCreated();

        // Mimic Buy Now pay: product_id present, required_area omitted — hydrate from cart.
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/order-summary?product_id='.$product->id.'&quantity=1')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 700)
            ->assertJsonPath('data.items.0.required_area', 100)
            ->assertJsonPath('data.items.0.line_total', 700);
    }

    public function test_buy_now_without_area_and_empty_cart_fails(): void
    {
        ServiceAreaPricing::saveGlobal('per_m2', 7, ServiceAreaPricing::emptyIncludes());

        $product = Product::create([
            'name' => 'service product',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/order-summary?product_id='.$product->id.'&quantity=1')
            ->assertStatus(422);
    }
}
