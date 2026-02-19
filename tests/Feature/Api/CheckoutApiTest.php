<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
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

    public function test_checkout_requires_auth(): void
    {
        $response = $this->postJson('/api/shop/checkout', [
            'full_name' => 'Test User',
            'phone_number' => '+971501234567',
            'street_address' => 'Street 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'payment_method' => 'cod',
        ]);
        $response->assertStatus(401);
    }

    public function test_checkout_requires_payment_method(): void
    {
        $response = $this->postJson('/api/shop/checkout', [
            'full_name' => 'Test User',
            'phone_number' => '+971501234567',
            'street_address' => 'Street 1',
            'city' => 'Dubai',
            'country' => 'UAE',
        ], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_checkout_with_inline_address_requires_address_fields_when_no_address_id(): void
    {
        $response = $this->postJson('/api/shop/checkout', [
            'payment_method' => 'cod',
        ], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['full_name', 'phone_number', 'street_address', 'city', 'country']);
    }

    public function test_checkout_with_cart_and_inline_address_cod_success(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 50.00,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->postJson('/api/shop/checkout', [
            'full_name' => 'Ahmed Hassan',
            'phone_number' => '+971501234567',
            'street_address' => 'Sheikh Zayed Road',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'zip_code' => '12345',
            'country' => 'UAE',
            'payment_method' => 'cod',
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Order placed. Cash on Delivery.');
        $response->assertJsonStructure(['data' => ['order' => ['id', 'user_id', 'total_amount', 'shipping_amount', 'payment_method', 'shipping_address_id']]]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'payment_method' => 'cod',
        ]);
        $this->assertDatabaseCount('carts', 0); // cart cleared
    }

    public function test_checkout_with_saved_address_id_and_cart(): void
    {
        $address = UserAddress::create([
            'user_id' => $this->user->id,
            'full_name' => 'Saved User',
            'phone_number' => '+971509999999',
            'street_address' => 'Saved Street',
            'city' => 'Abu Dhabi',
            'country' => 'UAE',
        ]);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 30, 'status' => 'active']);
        Cart::create(['user_id' => $this->user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $response = $this->postJson('/api/shop/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('data.order.shipping_address_id', $address->id);
    }

    public function test_checkout_with_explicit_items_and_inline_address(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 25.00,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/shop/checkout', [
            'full_name' => 'Test User',
            'phone_number' => '+971501234567',
            'street_address' => 'Street 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'payment_method' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'qty' => 2],
            ],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $order = $response->json('data.order');
        $this->assertNotNull($order['id']);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_checkout_empty_cart_and_no_items_returns_422(): void
    {
        $response = $this->postJson('/api/shop/checkout', [
            'full_name' => 'Test User',
            'phone_number' => '+971501234567',
            'street_address' => 'Street 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'payment_method' => 'cod',
        ], $this->authHeaders());

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cart is empty. Add items or send items in request.');
    }

    /** Simulate form-data (form-urlencoded) like Postman - empty address_id + inline fields. */
    public function test_checkout_form_style_request_success(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'status' => 'active',
        ]);
        Cart::create(['user_id' => $this->user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $response = $this->post('/api/shop/checkout', [
            'address_id' => '',
            'full_name' => 'Ahmed Hassan',
            'phone_number' => '+971501234567',
            'street_address' => 'Sheikh Zayed Road',
            'city' => 'Dubai',
            'country' => 'UAE',
            'payment_method' => 'cod',
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }
}
