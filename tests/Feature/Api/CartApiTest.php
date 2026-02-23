<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
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
}
