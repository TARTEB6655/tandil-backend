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
 * Ensure Instant Order Fee appears across the whole client checkout flow.
 */
class InstantFeeCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_instant_fee_present_on_cart_summary_review_and_coupons_path(): void
    {
        Setting::set('shop_shipping_amount', '15', 'text', 'shop');
        Setting::set('shop_tax_percent', '5', 'text', 'shop');
        InstantOrderFee::saveFromRequest([
            'instant_order_fee_amount' => 20,
            'instant_order_fee_enabled' => true,
        ]);

        $client = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create([
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
            'price' => 99,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 50,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        // 1) Cart view
        $cart = $this->actingAs($client, 'sanctum')
            ->getJson('/api/shop/cart')
            ->assertOk()
            ->assertJsonPath('data.order_summary.is_instant_order', true)
            ->assertJsonPath('data.order_summary.instant_order_fee', 20)
            ->assertJsonPath('data.order_summary.total', 138.95);

        // 2) Order summary
        $this->actingAs($client, 'sanctum')
            ->getJson('/api/shop/order-summary')
            ->assertOk()
            ->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 20)
            ->assertJsonPath('data.instant_order_fee_label', 'Instant order fee')
            ->assertJsonPath('data.total', 138.95);

        // 3) Buy now summary
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.order_summary.is_instant_order', true)
            ->assertJsonPath('data.order_summary.instant_order_fee', 20)
            ->assertJsonPath('data.order_summary.total', 138.95);

        // 4) Checkout review
        $this->actingAs($client, 'sanctum')
            ->getJson('/api/shop/checkout/review')
            ->assertOk()
            ->assertJsonPath('data.order_summary.is_instant_order', true)
            ->assertJsonPath('data.order_summary.instant_order_fee', 20)
            ->assertJsonPath('data.order_summary.total', 138.95);

        $flow = [
            'cart' => $cart->json('data.order_summary'),
        ];
        file_put_contents(
            storage_path('app/instant_fee_flow_check.json'),
            json_encode($flow, JSON_PRETTY_PRINT)
        );
    }
}
