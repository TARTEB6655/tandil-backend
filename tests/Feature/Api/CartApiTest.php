<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'client']);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    public function test_cart_add_requires_auth(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $response = $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $response->assertStatus(401);
    }

    public function test_cart_add_success(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 25.50,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Item added to cart.');
        $response->assertJsonPath('data.product_id', $product->id);
        $response->assertJsonPath('data.quantity', 2);
        $response->assertJsonPath('data.currency', 'AED');
        $response->assertJsonStructure(['data' => ['id', 'product_id', 'name', 'image_url', 'category', 'brand', 'current_price', 'quantity', 'line_total']]);

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_cart_add_increments_quantity_when_product_already_in_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'active']);

        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 3,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('data.quantity', 4);
        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);
    }

    public function test_cart_add_validation(): void
    {
        $response = $this->postJson('/api/shop/cart/add', [], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['product_id', 'quantity']);

        $response2 = $this->postJson('/api/shop/cart/add', [
            'product_id' => 99999,
            'quantity' => 1,
        ], $this->authHeaders());
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['product_id']);
    }

    public function test_cart_view_requires_auth(): void
    {
        $response = $this->getJson('/api/shop/cart');
        $response->assertStatus(401);
    }

    public function test_cart_view_returns_items_and_total(): void
    {
        $category = Category::factory()->create();
        $p1 = Product::factory()->create(['category_id' => $category->id, 'price' => 10, 'status' => 'active']);
        $p2 = Product::factory()->create(['category_id' => $category->id, 'price' => 20, 'status' => 'active']);

        Cart::create(['user_id' => $this->user->id, 'product_id' => $p1->id, 'quantity' => 2]);
        Cart::create(['user_id' => $this->user->id, 'product_id' => $p2->id, 'quantity' => 1]);

        $response = $this->getJson('/api/shop/cart', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['items', 'order_summary' => ['subtotal', 'discount', 'shipping', 'tax', 'total', 'currency']]]);
        $summary = $response->json('data.order_summary');
        $this->assertSame(40.0, (float) $summary['subtotal']);
        $expectedTotal = round($summary['subtotal'] - ($summary['discount'] ?? 0) + ($summary['shipping'] ?? 0) + ($summary['tax'] ?? 0), 2);
        $this->assertSame($expectedTotal, (float) $summary['total'], 'Order summary total should equal subtotal - discount + shipping + tax');
        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('name', $items[0]);
        $this->assertArrayHasKey('image_url', $items[0]);
        $this->assertArrayHasKey('category', $items[0]);
        $this->assertArrayHasKey('brand', $items[0]);
        $this->assertArrayHasKey('current_price', $items[0]);
        $this->assertArrayHasKey('original_price', $items[0]);
        $this->assertArrayHasKey('quantity', $items[0]);
        $this->assertArrayHasKey('line_total', $items[0]);
    }

    public function test_cart_view_empty(): void
    {
        $response = $this->getJson('/api/shop/cart', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.items', []);
        $response->assertJsonPath('data.order_summary.total', 0);
        $response->assertJsonPath('data.order_summary.currency', 'AED');
    }

    public function test_cart_remove_requires_auth(): void
    {
        $response = $this->deleteJson('/api/shop/cart/1');
        $response->assertStatus(401);
    }

    public function test_cart_remove_success(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'active']);
        $cartItem = Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->deleteJson('/api/shop/cart/' . $cartItem->id, [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Item removed from cart.');
        $this->assertDatabaseMissing('carts', ['id' => $cartItem->id]);
    }

    public function test_cart_remove_other_users_item_returns_404(): void
    {
        $otherUser = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'active']);
        $cartItem = Cart::create([
            'user_id' => $otherUser->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->deleteJson('/api/shop/cart/' . $cartItem->id, [], $this->authHeaders());

        $response->assertStatus(404);
        $this->assertDatabaseHas('carts', ['id' => $cartItem->id]);
    }

    public function test_cart_update_quantity(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'active']);
        $cartItem = Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->putJson('/api/shop/cart/' . $cartItem->id, ['quantity' => 5], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.quantity', 5);
        $response->assertJsonPath('data.id', $cartItem->id);
        $this->assertDatabaseHas('carts', ['id' => $cartItem->id, 'quantity' => 5]);
    }

    public function test_order_summary_empty_cart_returns_zero_subtotal(): void
    {
        $response = $this->getJson('/api/shop/order-summary', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.subtotal', 0);
        $response->assertJsonPath('data.total', 0);
        $response->assertJsonPath('data.wallet_available', false);
        $this->assertArrayNotHasKey('wallet_balance', $response->json('data'));
        $this->assertArrayNotHasKey('amount_due', $response->json('data'));
    }

    public function test_order_summary_get_includes_wallet_preview_with_use_wallet_query(): void
    {
        Setting::set('shop_shipping_amount', '10', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $this->user->update(['wallet_balance' => 100]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 99,
            'status' => 'active',
        ]);

        $q = http_build_query([
            'product_id' => $product->id,
            'quantity' => 1,
            'use_wallet' => '1',
        ]);

        $response = $this->getJson('/api/shop/order-summary?'.$q, $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.wallet_available', true);
        $response->assertJsonPath('data.use_wallet', true);
        $response->assertJsonPath('data.wallet_amount_applied', 100);
        $response->assertJsonPath('data.amount_due', 13.95);
        $response->assertJsonPath('data.total', 113.95);
    }

    public function test_order_summary_buy_now_query_without_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 15,
            'status' => 'active',
        ]);

        $response = $this->getJson(
            '/api/shop/order-summary?product_id=' . $product->id . '&quantity=10',
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertSame(150.0, (float) $response->json('data.subtotal'));
        // Default config: tax 5%, shipping 0 → total = 150 + 7.5 = 157.5
        $taxPercent = (float) config('shop.tax_percent', 5);
        $expectedTotal = round(150 + (150 * $taxPercent / 100), 2);
        $this->assertSame($expectedTotal, (float) $response->json('data.total'));
    }

    public function test_order_summary_buy_now_accepts_qty_alias(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 20,
            'status' => 'active',
        ]);

        $response = $this->getJson(
            '/api/shop/order-summary?product_id=' . $product->id . '&qty=10',
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertSame(200.0, (float) $response->json('data.subtotal'));
    }

    public function test_order_summary_cart_quantity_10_matches_line_total(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 15,
            'status' => 'active',
        ]);

        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertSame(150.0, (float) $response->json('data.subtotal'));
    }

    public function test_buy_now_summary_uses_cart_and_returns_no_item_payload(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 65,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonMissingPath('data.item');
        $this->assertSame(650.0, (float) $response->json('data.order_summary.subtotal'));
    }

    public function test_buy_now_summary_ignores_product_specific_payload_and_uses_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 30,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [
            'qty' => 10,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertSame(300.0, (float) $response->json('data.order_summary.subtotal'));
    }

    public function test_buy_now_summary_accepts_only_wallet_fields(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 30,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [
            'use_wallet' => true,
            'wallet_amount' => 10,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertSame(300.0, (float) $response->json('data.order_summary.subtotal'));
    }

    public function test_buy_now_summary_includes_wallet_balance_and_amount_due_matches_total_when_wallet_off(): void
    {
        Setting::set('shop_shipping_amount', '10', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $this->user->update(['wallet_balance' => 100]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 99,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.order_summary.wallet_available', true);
        $response->assertJsonPath('data.order_summary.wallet_balance', 100);
        $response->assertJsonPath('data.order_summary.use_wallet', false);
        $response->assertJsonPath('data.order_summary.wallet_amount_applied', 0);
        $this->assertSame(99.0, (float) $response->json('data.order_summary.subtotal'));
        $response->assertJsonPath('data.order_summary.total', 113.95);
        $response->assertJsonPath('data.order_summary.amount_due', 113.95);
    }

    public function test_buy_now_summary_use_wallet_true_applies_wallet_to_amount_due(): void
    {
        Setting::set('shop_shipping_amount', '10', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $this->user->update(['wallet_balance' => 100]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 99,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [
            'use_wallet' => true,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.order_summary.wallet_available', true);
        $response->assertJsonPath('data.order_summary.use_wallet', true);
        $response->assertJsonPath('data.order_summary.wallet_amount_applied', 100);
        $response->assertJsonPath('data.order_summary.amount_due', 13.95);
        $response->assertJsonPath('data.order_summary.total', 113.95);
    }

    public function test_buy_now_summary_wallet_amount_caps_requested_amount(): void
    {
        Setting::set('shop_shipping_amount', '0', 'number', 'shop');
        Setting::set('shop_tax_percent', '0', 'number', 'shop');

        $this->user->update(['wallet_balance' => 80]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 100,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [
            'use_wallet' => true,
            'wallet_amount' => 30,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.order_summary.wallet_available', true);
        $response->assertJsonPath('data.order_summary.wallet_amount_applied', 30);
        $response->assertJsonPath('data.order_summary.amount_due', 70);
    }

    public function test_buy_now_summary_aed_minimum_card_rule_reduces_wallet_when_card_due_would_be_under_2(): void
    {
        config(['shop.currency' => 'aed']);
        Setting::set('shop_shipping_amount', '10', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $this->user->update(['wallet_balance' => 112.95]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 99,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [
            'use_wallet' => true,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.order_summary.wallet_available', true);
        $response->assertJsonPath('data.order_summary.total', 113.95);
        $response->assertJsonPath('data.order_summary.wallet_amount_applied', 111.95);
        $this->assertEqualsWithDelta(2.0, (float) $response->json('data.order_summary.amount_due'), 0.001);
    }

    public function test_buy_now_summary_zero_wallet_omits_wallet_lines_even_when_use_wallet_true(): void
    {
        Setting::set('shop_shipping_amount', '10', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $this->user->update(['wallet_balance' => 0]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 99,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [
            'use_wallet' => true,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $summary = $response->json('data.order_summary');
        $this->assertIsArray($summary);
        $response->assertJsonPath('data.order_summary.wallet_available', false);
        $this->assertArrayNotHasKey('wallet_balance', $summary);
        $this->assertArrayNotHasKey('use_wallet', $summary);
        $this->assertArrayNotHasKey('wallet_amount_applied', $summary);
        $this->assertArrayNotHasKey('amount_due', $summary);
        $response->assertJsonPath('data.order_summary.total', 113.95);
    }
}
