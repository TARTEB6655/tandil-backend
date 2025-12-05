<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test anyone can view products
     */
    public function test_anyone_can_view_products()
    {
        $this->createProduct();
        $this->createProduct();

        $response = $this->getJson('/api/shop/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'price']
                    ]
                ]
            ]);
    }

    /**
     * Test anyone can view single product
     */
    public function test_anyone_can_view_single_product()
    {
        $product = $this->createProduct();

        $response = $this->getJson("/api/shop/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'name', 'price']
            ]);
    }

    /**
     * Test authenticated user can checkout
     */
    public function test_authenticated_user_can_checkout()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $product = $this->createProduct();

        $response = $this->postJson('/api/shop/checkout', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 2]
            ],
            'total_amount' => $product->price * 2,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['order', 'payment']
            ]);
    }

    /**
     * Test authenticated user can view orders
     */
    public function test_authenticated_user_can_view_orders()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $order = $this->createOrder(['user_id' => $customer->id]);

        $response = $this->getJson('/api/shop/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test authenticated user can view single order
     */
    public function test_authenticated_user_can_view_single_order()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $order = $this->createOrder(['user_id' => $customer->id]);

        $response = $this->getJson("/api/shop/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test checkout requires authentication
     */
    public function test_checkout_requires_authentication()
    {
        $response = $this->postJson('/api/shop/checkout', []);

        $response->assertStatus(401);
    }
}

