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

/**
 * Dummy product + Instant Fee payloads for frontend field mapping.
 */
class InstantFeeFrontendPayloadDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dummy_product_instant_fee_payloads_for_frontend(): void
    {
        Setting::set('shop_shipping_amount', '15', 'text', 'shop');
        Setting::set('shop_tax_percent', '5', 'text', 'shop');
        InstantOrderFee::saveFromRequest([
            'instant_order_fee_amount' => 20,
            'instant_order_fee_enabled' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        $category = Category::factory()->create([
            'name' => 'Instant Fee Demo Category',
            'shipping_cost' => 15,
            'tax_percentage' => 5,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => null,
            'name' => 'Dummy Instant Fee Product',
            'sku' => 'DUMMY-INSTANT-FEE-001',
            'type' => 'product',
            'price' => 99,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 50,
            'product_type' => 'simple',
        ]);

        Cart::create([
            'user_id' => $client->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 99,
        ]);

        $this->assertDatabaseHas('carts', [
            'user_id' => $client->id,
            'product_id' => $product->id,
        ]);

        $putFee = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/settings/instant-order-fee', [
                'instant_order_fee_amount' => 20,
                'instant_order_fee_enabled' => true,
            ])
            ->assertOk();

        $getFee = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/settings/instant-order-fee')
            ->assertOk();

        $shopSettings = $this->getJson('/api/shop/settings')->assertOk();

        $cart = $this->actingAs($client, 'sanctum')
            ->getJson('/api/shop/cart')
            ->assertOk();

        $summary = $this->actingAs($client, 'sanctum')
            ->getJson('/api/shop/order-summary')
            ->assertOk();

        $buyNow = $this->actingAs($client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $cart->assertJsonPath('data.order_summary.is_instant_order', true)
            ->assertJsonPath('data.order_summary.instant_order_fee', 20)
            ->assertJsonPath('data.order_summary.subtotal', 99)
            ->assertJsonPath('data.order_summary.total', 138.95);

        $summary->assertJsonPath('data.instant_order_fee', 20)
            ->assertJsonPath('data.total', 138.95);

        $buyNow->assertJsonPath('data.order_summary.instant_order_fee', 20);

        $payload = [
            'frontend_fields_to_show' => [
                'is_instant_order' => 'Show Instant Order Fee row only when true',
                'instant_order_fee' => 'AED amount (e.g. 20)',
                'instant_order_fee_label' => 'Label text: Instant order fee',
                'total' => 'Already includes instant_order_fee — do not add fee twice',
            ],
            'screens' => [
                'Shopping Cart' => 'GET /api/shop/cart → data.order_summary',
                'Checkout Payment' => 'GET /api/shop/order-summary → data',
                'Buy Now' => 'POST /api/shop/buy-now/summary → data.order_summary',
                'Shop settings (optional)' => 'GET /api/shop/settings → instant_order_fee_amount',
                'Admin fee screen' => 'GET/PUT /api/admin/settings/instant-order-fee',
            ],
            'setup' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'type' => $product->type,
                'price' => 99,
                'fee' => 20,
                'expected_total' => 138.95,
                'math' => 'subtotal 99 + shipping 15 + tax 4.95 + instant_order_fee 20 = 138.95',
            ],
            'responses' => [
                'PUT /api/admin/settings/instant-order-fee' => $putFee->json(),
                'GET /api/admin/settings/instant-order-fee' => $getFee->json(),
                'GET /api/shop/settings' => $shopSettings->json(),
                'GET /api/shop/cart' => $cart->json(),
                'GET /api/shop/order-summary' => $summary->json(),
                'POST /api/shop/buy-now/summary' => $buyNow->json(),
            ],
        ];

        $path = storage_path('app/instant_fee_frontend_payloads.json');
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertFileExists($path);
    }
}
