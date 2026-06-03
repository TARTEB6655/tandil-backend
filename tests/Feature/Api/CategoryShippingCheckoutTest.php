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
        $small = Category::factory()->create(['shipping_amount' => 15]);
        $large = Category::factory()->create(['shipping_amount' => 45]);

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
        $category = Category::factory()->create(['shipping_amount' => 30]);
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
        $category = Category::factory()->create(['shipping_amount' => null]);

        $response = $this->putJson('/api/admin/settings/shop/category-shipping', [
            'rates' => [
                ['category_id' => $category->id, 'shipping_amount' => 22.5],
            ],
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.rates.0.shipping_amount', 22.5);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'shipping_amount' => 22.5,
        ]);
    }
}
