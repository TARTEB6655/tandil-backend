<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\InstantOrderFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartProduct210InstantFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_apis_include_instant_fee_for_product_210(): void
    {
        Setting::set('shop_shipping_amount', '15', 'text', 'shop');
        Setting::set('shop_tax_percent', '5', 'text', 'shop');
        InstantOrderFee::saveFromRequest([
            'instant_order_fee_amount' => 20,
            'instant_order_fee_enabled' => true,
        ]);

        $client = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create([
            'id' => 20,
            'name' => 'Trees and Palms Care',
            'shipping_cost' => 15,
            'tax_percentage' => 5,
        ]);

        $product = Product::factory()->create([
            'id' => 210,
            'category_id' => $category->id,
            'vendor_id' => null,
            'name' => 'simple product',
            'type' => 'product',
            'product_type' => 'simple',
            'sku' => 'sku-002',
            'price' => 99,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 50,
        ]);

        $this->assertSame(210, $product->id);

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$client->createToken('t')->plainTextToken,
        ];

        $this->postJson('/api/shop/cart/add', [
            'product_id' => 210,
            'quantity' => 1,
        ], $headers)->assertCreated();

        $cart = $this->actingAs($client, 'sanctum')
            ->getJson('/api/shop/cart')
            ->assertOk();

        $summary = $this->actingAs($client, 'sanctum')
            ->getJson('/api/shop/order-summary')
            ->assertOk();

        $cart->assertJsonPath('data.items.0.product_id', 210)
            ->assertJsonPath('data.items.0.name', 'simple product')
            ->assertJsonPath('data.order_summary.is_instant_order', true)
            ->assertJsonPath('data.order_summary.instant_order_fee', 20)
            ->assertJsonPath('data.order_summary.instant_order_fee_label', 'Instant order fee')
            ->assertJsonPath('data.order_summary.subtotal', 99)
            ->assertJsonPath('data.order_summary.shipping', 15)
            ->assertJsonPath('data.order_summary.tax', 4.95)
            ->assertJsonPath('data.order_summary.total', 138.95);

        $summary->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 20)
            ->assertJsonPath('data.total', 138.95);

        // Helpful dump for frontend / Postman mapping
        $payload = [
            'GET /api/shop/cart order_summary' => $cart->json('data.order_summary'),
            'GET /api/shop/order-summary' => $summary->json('data'),
        ];
        file_put_contents(
            storage_path('app/cart_210_fee_response.json'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
