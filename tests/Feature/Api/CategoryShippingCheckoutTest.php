<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryShippingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('shop_shipping_amount', '10', 'text', 'shop');
        Setting::set('shop_tax_percent', '0', 'text', 'shop');

        $this->user = User::factory()->create(['role' => 'client']);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_order_summary_uses_per_category_shipping_fee(): void
    {
        $small = Category::factory()->create(['shipping_cost' => 15, 'tax_percentage' => 0]);
        $large = Category::factory()->carDelivery()->create(['shipping_cost' => 45, 'tax_percentage' => 0]);

        $p1 = Product::factory()->create([
            'category_id' => $small->id,
            'price' => 50,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        $p2 = Product::factory()->create([
            'category_id' => $large->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        Cart::create(['user_id' => $this->user->id, 'product_id' => $p1->id, 'quantity' => 1, 'unit_price' => 50]);
        Cart::create(['user_id' => $this->user->id, 'product_id' => $p2->id, 'quantity' => 1, 'unit_price' => 100]);

        $response = $this->getJson('/api/shop/order-summary', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertSame(150.0, (float) $response->json('data.subtotal'));
        $this->assertSame(60.0, (float) $response->json('data.shipping'));
        $this->assertSame(210.0, (float) $response->json('data.total'));
        $this->assertCount(2, $response->json('data.category_shipping_breakdown'));
    }

    public function test_buy_now_uses_category_shipping_for_product(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 30, 'tax_percentage' => 0]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 80,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        $response = $this->getJson(
            '/api/shop/order-summary?product_id='.$product->id.'&quantity=1',
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertSame(80.0, (float) $response->json('data.subtotal'));
        $this->assertSame(30.0, (float) $response->json('data.shipping'));
    }

    public function test_admin_can_sync_category_shipping_rates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        $category = Category::factory()->create(['shipping_cost' => null, 'tax_percentage' => null]);

        $response = $this->putJson('/api/admin/settings/shop/category-shipping', [
            'rates' => [
                [
                    'category_id' => $category->id,
                    'shipping_cost' => 22.5,
                    'tax_percentage' => 10,
                ],
            ],
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.rates.0.shipping_cost', 22.5);
        $response->assertJsonPath('data.rates.0.tax_percentage', 10);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'shipping_cost' => 22.5,
            'tax_percentage' => 10,
        ]);
    }

    public function test_order_summary_breakdown_lists_category_shipping_cost(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 12, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 40,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        Cart::create(['user_id' => $this->user->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 40]);

        $response = $this->getJson('/api/shop/order-summary', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.category_shipping_breakdown.0.shipping_cost', 12);
        $response->assertJsonPath('data.shipping', 12);
    }

    public function test_order_summary_uses_per_category_tax_rates(): void
    {
        Setting::set('shop_tax_percent', '5', 'text', 'shop');

        $lowTax = Category::factory()->create(['shipping_cost' => 10, 'tax_percentage' => 5]);
        $highTax = Category::factory()->carDelivery()->create(['shipping_cost' => 20, 'tax_percentage' => 10]);

        $p1 = Product::factory()->create([
            'category_id' => $lowTax->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        $p2 = Product::factory()->create([
            'category_id' => $highTax->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        Cart::create(['user_id' => $this->user->id, 'product_id' => $p1->id, 'quantity' => 1, 'unit_price' => 100]);
        Cart::create(['user_id' => $this->user->id, 'product_id' => $p2->id, 'quantity' => 1, 'unit_price' => 100]);

        $response = $this->getJson('/api/shop/order-summary', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertSame(200.0, (float) $response->json('data.subtotal'));
        $this->assertSame(30.0, (float) $response->json('data.shipping'));
        $this->assertSame(15.0, (float) $response->json('data.tax'));
        $this->assertTrue((bool) $response->json('data.uses_category_tax'));
        $this->assertSame(245.0, (float) $response->json('data.total'));
    }

    public function test_category_api_returns_shipping_and_tax_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Electronics',
            'shipping_cost' => 150,
            'tax_percentage' => 18,
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.shipping_cost', 150);
        $response->assertJsonPath('data.tax_percentage', 18);
        $this->assertArrayNotHasKey('shipping_type', $response->json('data'));
    }
}
