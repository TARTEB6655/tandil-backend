<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    }

    public function test_get_product_details_returns_200_and_product_data_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 99.99,
            'status' => 'active',
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/admin/products/' . $product->id, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'name',
                'price',
                'status',
                'image_url',
            ],
        ]);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.id', $product->id);
        $response->assertJsonPath('data.name', 'Test Product');
    }

    public function test_get_product_details_returns_404_for_missing_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/admin/products/99999', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(404);
    }

    public function test_get_product_details_returns_401_without_token(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $response = $this->getJson('/api/admin/products/' . $product->id, [
            'Accept' => 'application/json',
        ]);
        $response->assertStatus(401);
    }
}
