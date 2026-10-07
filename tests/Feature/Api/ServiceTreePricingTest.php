<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceTreePricingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Category $category;

    private Product $serviceProduct;

    protected function setUp(): void
    {
        parent::setUp();
        ServiceTreePricing::clearSchemaCache();
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

        ServiceAreaPricing::saveGlobal(ServiceAreaPricing::TYPE_FIXED, 0, ServiceAreaPricing::emptyIncludes());

        $service = Service::create([
            'name' => 'Landscaping',
            'slug' => 'landscaping-'.uniqid(),
            'is_active' => true,
        ]);
        $this->serviceProduct = Product::create([
            'name' => 'Garden Service',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 100,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'status' => 'active',
            'stock' => 999,
        ]);
        $service->products()->attach($this->serviceProduct->id);
    }

    public function test_admin_can_configure_tree_palm_pricing_on_dedicated_ui_api(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->post('/api/admin/settings/tree-palm-pricing', [
                'show_tree_options' => '1',
                'price_per_tree' => '50',
                'price_per_palm_tree' => '80',
                'price_includes' => [
                    'materials' => '1',
                    'installation' => '1',
                    'labor' => '1',
                    'transportation' => '1',
                    'delivery' => '1',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.show_tree_options', true)
            ->assertJsonPath('data.price_per_tree', 50)
            ->assertJsonPath('data.price_per_palm_tree', 80)
            ->assertJsonPath('data.price_includes.materials', true)
            ->assertJsonMissingPath('data.pricing_type')
            ->assertJsonMissingPath('data.price_per_m2');

        $fields = ServiceAreaPricing::productApiFields($this->serviceProduct->fresh());
        $this->assertTrue($fields['show_tree_options']);
        $this->assertSame(50.0, (float) $fields['tree_pricing']['trees']['unit_price']);
        $this->assertSame(80.0, (float) $fields['tree_pricing']['palm_trees']['unit_price']);
        $this->assertTrue($fields['tree_pricing']['optional']);
        $this->assertFalse($fields['tree_pricing']['requires_quantity']);
    }

    public function test_old_service_pricing_api_does_not_accept_tree_fields_as_required_mix(): void
    {
        // Old API stays fixed/per_m2 only — tree fields are ignored (dedicated API).
        $this->actingAs($this->admin, 'sanctum')
            ->post('/api/admin/settings/service-pricing', [
                'pricing_type' => 'fixed',
                'price' => '0',
                'show_tree_options' => '1',
                'price_per_tree' => '50',
                'price_per_palm_tree' => '80',
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonMissingPath('data.price_per_tree');

        $this->assertFalse(ServiceTreePricing::globalConfig()['show_tree_options']);
    }

    public function test_cart_add_without_tree_quantity_keeps_base_service_price(): void
    {
        ServiceTreePricing::saveGlobal(true, 50, 80);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.line_total', 100)
            ->assertJsonPath('data.tree_quantity', null)
            ->assertJsonPath('data.palm_tree_quantity', null);

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->client->id,
            'product_id' => $this->serviceProduct->id,
            'tree_quantity' => null,
            'palm_tree_quantity' => null,
        ]);
    }

    public function test_cart_add_with_tree_and_palm_quantities_adds_unit_pricing(): void
    {
        ServiceTreePricing::saveGlobal(true, 50, 80);

        // base 100 + (2 × 50) + (1 × 80) = 280
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'tree_quantity' => 2,
                'palm_tree_quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.line_total', 280)
            ->assertJsonPath('data.tree_quantity', 2)
            ->assertJsonPath('data.palm_tree_quantity', 1)
            ->assertJsonPath('data.tree_palm_addon', 180);
    }

    public function test_buy_now_summary_accepts_optional_tree_quantities(): void
    {
        ServiceTreePricing::saveGlobal(true, 50, 80);

        // base 100 + (3 × 50) = 250 — checkout shows base + Trees as separate lines
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'is_buy_now' => true,
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'trees' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 250)
            ->assertJsonPath('data.order_summary.items.0.line_kind', 'service')
            ->assertJsonPath('data.order_summary.items.0.line_total', 100)
            ->assertJsonPath('data.order_summary.items.0.tree_quantity', 3)
            ->assertJsonPath('data.order_summary.items.1.line_kind', 'tree_addon')
            ->assertJsonPath('data.order_summary.items.1.quantity', 3)
            ->assertJsonPath('data.order_summary.items.1.line_total', 150);
    }

    public function test_buy_now_summary_shows_palm_trees_as_separate_line_item(): void
    {
        ServiceTreePricing::saveGlobal(true, 50, 80);

        // base 100 + (2 × 80) = 260
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'is_buy_now' => true,
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'palm_tree_quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 260)
            ->assertJsonPath('data.order_summary.items.0.line_kind', 'service')
            ->assertJsonPath('data.order_summary.items.0.line_total', 100)
            ->assertJsonPath('data.order_summary.items.1.line_kind', 'palm_addon')
            ->assertJsonPath('data.order_summary.items.1.quantity', 2)
            ->assertJsonPath('data.order_summary.items.1.unit_price', 80)
            ->assertJsonPath('data.order_summary.items.1.line_total', 160);

        $palmName = (string) data_get($response->json(), 'data.order_summary.items.1.name');
        $this->assertStringContainsString('Palm Trees', $palmName);
        $this->assertStringContainsString('2', $palmName);
    }

    public function test_buy_now_summary_omits_addon_lines_when_quantity_omitted(): void
    {
        ServiceTreePricing::saveGlobal(true, 50, 80);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'is_buy_now' => true,
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 100)
            ->assertJsonPath('data.order_summary.items.0.line_total', 100)
            ->assertJsonCount(1, 'data.order_summary.items');
    }

    public function test_tree_options_hidden_when_admin_toggle_off(): void
    {
        ServiceTreePricing::saveGlobal(false, 50, 80);

        $fields = ServiceAreaPricing::productApiFields($this->serviceProduct->fresh());
        $this->assertFalse($fields['show_tree_options']);
        $this->assertFalse($fields['tree_pricing']['enabled']);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'tree_quantity' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.line_total', 100)
            ->assertJsonPath('data.tree_quantity', null);
    }

    public function test_shop_product_rejects_tree_quantities(): void
    {
        ServiceTreePricing::saveGlobal(true, 50, 80);
        $shop = Product::create([
            'name' => 'Soil Bag',
            'type' => 'product',
            'category_id' => $this->category->id,
            'price' => 25,
            'status' => 'active',
            'stock' => 50,
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $shop->id,
                'quantity' => 1,
                'tree_quantity' => 2,
            ])
            ->assertStatus(422);
    }

    public function test_all_palm_tree_related_apis_return_proper_responses(): void
    {
        // --- 1) Admin GET (defaults / empty) ---
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/settings/tree-palm-pricing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'show_tree_options',
                    'price_per_tree',
                    'price_per_palm_tree',
                    'price_includes',
                ],
            ])
            ->assertJsonMissingPath('data.pricing_type')
            ->assertJsonMissingPath('data.price_per_m2');

        // --- 2) Admin PUT UI fields only ---
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/tree-palm-pricing', [
                'show_tree_options' => true,
                'price_per_tree' => 50,
                'price_per_palm_tree' => 80,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => false,
                    'delivery' => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.show_tree_options', true)
            ->assertJsonPath('data.price_per_tree', 50)
            ->assertJsonPath('data.price_per_palm_tree', 80)
            ->assertJsonPath('data.price_includes.materials', true)
            ->assertJsonPath('data.price_includes.transportation', false);

        // --- 3) Admin GET after save ---
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/settings/tree-palm-pricing')
            ->assertOk()
            ->assertJsonPath('data.price_per_palm_tree', 80);

        // --- 4) Shop product detail exposes optional tree/palm options ---
        $this->getJson('/api/shop/products/'.$this->serviceProduct->id)
            ->assertOk()
            ->assertJsonPath('data.show_tree_options', true)
            ->assertJsonPath('data.tree_pricing.enabled', true)
            ->assertJsonPath('data.tree_pricing.optional', true)
            ->assertJsonPath('data.tree_pricing.trees.unit_price', 50)
            ->assertJsonPath('data.tree_pricing.palm_trees.unit_price', 80);

        // --- 5) Cart add with trees + palms (merged line_total on cart row) ---
        // base 100 + (2×50) + (2×80) = 360
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'tree_quantity' => 2,
                'palm_tree_quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.line_total', 360)
            ->assertJsonPath('data.base_line_total', 100)
            ->assertJsonPath('data.tree_quantity', 2)
            ->assertJsonPath('data.palm_tree_quantity', 2)
            ->assertJsonPath('data.price_per_palm_tree', 80)
            ->assertJsonPath('data.tree_palm_addon', 260);

        // --- 6) GET cart ---
        $cart = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.palm_tree_quantity', 2)
            ->assertJsonPath('data.items.0.line_total', 360)
            ->assertJsonPath('data.order_summary.subtotal', 360);

        // --- 7) GET order-summary: separate checkout lines ---
        $orderSummary = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/order-summary')
            ->assertOk()
            ->assertJsonPath('data.subtotal', 360)
            ->assertJsonPath('data.items.0.line_kind', 'service')
            ->assertJsonPath('data.items.0.line_total', 100)
            ->assertJsonPath('data.items.1.line_kind', 'tree_addon')
            ->assertJsonPath('data.items.1.line_total', 100)
            ->assertJsonPath('data.items.2.line_kind', 'palm_addon')
            ->assertJsonPath('data.items.2.quantity', 2)
            ->assertJsonPath('data.items.2.unit_price', 80)
            ->assertJsonPath('data.items.2.line_total', 160)
            ->assertJsonCount(3, 'data.items');

        $palmName = (string) data_get($orderSummary->json(), 'data.items.2.name');
        $this->assertStringContainsString('Palm Trees', $palmName);

        // Sum of display lines equals subtotal
        $itemSum = collect(data_get($orderSummary->json(), 'data.items', []))
            ->sum(fn ($row) => (float) ($row['line_total'] ?? 0));
        $this->assertSame(360.0, round($itemSum, 2));

        // --- 8) Buy-now summary (same basket via product_id) ---
        $buyNow = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'is_buy_now' => true,
                'product_id' => $this->serviceProduct->id,
                'quantity' => 1,
                'tree_quantity' => 2,
                'palm_tree_quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 360)
            ->assertJsonPath('data.order_summary.items.0.line_kind', 'service')
            ->assertJsonPath('data.order_summary.items.0.line_total', 100)
            ->assertJsonPath('data.order_summary.items.1.line_kind', 'tree_addon')
            ->assertJsonPath('data.order_summary.items.2.line_kind', 'palm_addon')
            ->assertJsonPath('data.order_summary.items.2.line_total', 160)
            ->assertJsonCount(3, 'data.order_summary.items');

        // --- 9) Checkout review ---
        $review = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/checkout/review')
            ->assertOk();

        $reviewItems = data_get($review->json(), 'data.order_summary.items')
            ?? data_get($review->json(), 'data.items');
        $this->assertIsArray($reviewItems);
        $this->assertCount(3, $reviewItems);
        $this->assertSame('service', $reviewItems[0]['line_kind'] ?? null);
        $this->assertSame('palm_addon', $reviewItems[2]['line_kind'] ?? null);
        $this->assertSame(160.0, (float) ($reviewItems[2]['line_total'] ?? 0));
        $this->assertSame(
            360.0,
            (float) (data_get($review->json(), 'data.order_summary.subtotal')
                ?? data_get($review->json(), 'data.subtotal'))
        );

        // Persist a compact proof of real response shapes for manual review.
        file_put_contents(
            storage_path('app/palm_tree_api_response_proof.json'),
            json_encode([
                'cart_item' => data_get($cart->json(), 'data.items.0'),
                'order_summary' => $orderSummary->json('data'),
                'buy_now_order_summary' => $buyNow->json('data.order_summary'),
                'checkout_review_items' => $reviewItems,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
