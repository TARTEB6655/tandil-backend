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
                'success',
                'data' => [
                    '*' => ['id', 'name', 'price']
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
                'success',
                'data' => ['id', 'name', 'price']
            ]);
    }

    /**
     * Test anyone can view product categories
     */
    public function test_anyone_can_view_product_categories()
    {
        $response = $this->getJson('/api/shop/products/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data'
            ]);
    }

    /**
     * Test anyone can view products by category
     */
    public function test_anyone_can_view_products_by_category()
    {
        $category = \App\Models\Category::factory()->create(['name' => 'Fertilizers']);
        $this->createProduct(['category_id' => $category->id, 'name' => 'Product In Category', 'status' => 'active']);
        $otherCategory = \App\Models\Category::factory()->create();
        $this->createProduct(['category_id' => $otherCategory->id, 'name' => 'Other Product', 'status' => 'active']);

        $response = $this->getJson("/api/shop/products/category/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', 'Fertilizers')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'category',
                    'products',
                    'pagination'
                ]
            ]);

        $products = $response->json('data.products');
        $this->assertCount(1, $products);
        $this->assertSame($category->id, $products[0]['category_id']);
    }

    /**
     * Test anyone can list shop products filtered by category_id query param
     */
    public function test_anyone_can_list_shop_products_filter_by_category_id()
    {
        $category = \App\Models\Category::factory()->create(['name' => 'Seeds']);
        $this->createProduct(['category_id' => $category->id, 'name' => 'Seed Product', 'status' => 'active']);
        $this->createProduct(['category_id' => $category->id, 'name' => 'Another Seed', 'status' => 'active']);
        $otherCat = \App\Models\Category::factory()->create();
        $this->createProduct(['category_id' => $otherCat->id, 'name' => 'Other', 'status' => 'active']);

        $response = $this->getJson('/api/shop/products?category_id=' . $category->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertSame($category->id, $product['category_id']);
        }
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
                'success',
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
                'success',
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




