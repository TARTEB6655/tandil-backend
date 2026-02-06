<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    }

    public function test_services_index_returns_only_active_categories_with_shape(): void
    {
        Category::factory()->create(['name' => 'Active Service', 'is_active' => true]);
        Category::factory()->create(['name' => 'Another Active', 'is_active' => true]);
        Category::factory()->create(['name' => 'Inactive Service', 'is_active' => false]);

        $response = $this->getJson('/api/services', ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $payload = $response->json('data');
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('pagination', $payload);
        $data = $payload['data'];
        $this->assertCount(2, $data);
        foreach ($data as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('image', $item);
            $this->assertArrayHasKey('image_url', $item);
            $this->assertArrayHasKey('is_active', $item);
            $this->assertArrayHasKey('coming_soon', $item);
            $this->assertArrayHasKey('products_count', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertTrue($item['is_active']);
            $this->assertFalse($item['coming_soon']);
        }
    }

    public function test_services_categories_returns_active_only_with_same_shape(): void
    {
        Category::factory()->create(['name' => 'Cat A', 'is_active' => true]);
        Category::factory()->create(['name' => 'Cat B', 'is_active' => false]);

        $response = $this->getJson('/api/services/categories', ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $item = $data[0];
        $this->assertArrayHasKey('image_url', $item);
        $this->assertArrayHasKey('is_active', $item);
        $this->assertArrayHasKey('coming_soon', $item);
        $this->assertArrayHasKey('products_count', $item);
        $this->assertSame('Cat A', $item['name']);
    }

    public function test_services_category_by_id_returns_category_and_products_with_image_url(): void
    {
        $category = Category::factory()->create(['name' => 'Main', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Product One',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/services/category/' . $category->id, ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertSame($category->id, $data['id']);
        $this->assertArrayHasKey('image_url', $data);
        $this->assertArrayHasKey('products', $data);
        $products = $data['products'];
        $this->assertCount(1, $products);
        $this->assertArrayHasKey('id', $products[0]);
        $this->assertArrayHasKey('name', $products[0]);
        $this->assertArrayHasKey('image_url', $products[0]);
        $this->assertArrayHasKey('slug', $products[0]);
        $this->assertSame('Product One', $products[0]['name']);
    }

    public function test_services_show_returns_single_active_category(): void
    {
        $category = Category::factory()->create(['name' => 'Single', 'is_active' => true]);

        $response = $this->getJson('/api/services/' . $category->id, ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Single');
        $response->assertJsonPath('data.image_url', $category->image_url);
        $response->assertJsonPath('data.products_count', 0);
    }

    public function test_services_show_404_for_inactive_category(): void
    {
        $category = Category::factory()->create(['name' => 'Inactive', 'is_active' => false]);

        $response = $this->getJson('/api/services/' . $category->id, ['Accept' => 'application/json']);

        $response->assertStatus(404);
    }
}
