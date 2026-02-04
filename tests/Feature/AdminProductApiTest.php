<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_product_update_api_updates_product_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Original Name',
            'price' => 50.00,
            'status' => 'draft',
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->putJson('/api/admin/products/' . $product->id, [
            'name' => 'Updated Name',
            'price' => 199.99,
            'status' => 'active',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Product updated successfully.');
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.price', 199.99);
        $response->assertJsonPath('data.status', 'active');

        $product->refresh();
        $this->assertSame('Updated Name', $product->name);
        $this->assertSame(199.99, (float) $product->price);
        $this->assertSame('active', $product->status);
    }

    public function test_product_update_api_updates_product_image_with_main_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Product With Image',
            'sku' => 'sku-update-img-' . uniqid(),
            'handle' => 'product-update-img-' . uniqid(),
        ]);
        $token = $admin->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('main_image.jpg', 100, 100);

        $response = $this->call(
            'PUT',
            '/api/admin/products/' . $product->id,
            [
                'name' => 'Product With Image',
                'sku' => $product->sku,
                'handle' => $product->handle,
            ],
            [],
            ['main_image' => $file],
            [
                'HTTP_Authorization' => 'Bearer ' . $token,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Product updated successfully.');
        $json = $response->json('data');
        $this->assertArrayHasKey('image', $json);
        $this->assertArrayHasKey('image_url', $json);
        $this->assertNotEmpty($json['image']);
        $this->assertNotEmpty($json['image_url']);

        $product->refresh();
        $this->assertNotEmpty($product->image);
        $this->assertStringContainsString('products/', $product->image);
    }
}
