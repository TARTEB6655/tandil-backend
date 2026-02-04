<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
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

    public function test_product_detail_api_returns_deduplicated_images_when_db_has_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Product With Duplicate Images',
            'sku' => 'sku-dup-' . uniqid(),
            'handle' => 'product-dup-' . uniqid(),
            'image' => 'products/same.png',
        ]);
        $path = 'products/same.png';
        ProductImage::create(['product_id' => $product->id, 'image_path' => $path, 'sort_order' => 0, 'is_primary' => true]);
        ProductImage::create(['product_id' => $product->id, 'image_path' => $path, 'sort_order' => 1, 'is_primary' => false]);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/admin/products/' . $product->id, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $data = $response->json('data');
        $this->assertArrayHasKey('images', $data);
        $this->assertCount(1, $data['images'], 'API should return deduplicated images (1 unique path, not 2 rows)');
        $this->assertSame($path, $data['images'][0]['image_path'] ?? null);
    }

    /**
     * Test GET /api/admin/products/{id} (product detail API):
     * - Returns 200 with full product data
     * - images array has no duplicates (each image_path appears once)
     * - Each image has image_url (full URL, no truncation)
     * - image and image_url at root point to primary image
     */
    public function test_product_detail_api_full_size_images_no_duplication(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Detail API Product',
            'sku' => 'sku-detail-' . uniqid(),
            'handle' => 'product-detail-' . uniqid(),
        ]);
        $token = $admin->createToken('test')->plainTextToken;
        $file1 = UploadedFile::fake()->image('primary.jpg', 200, 200);
        $file2 = UploadedFile::fake()->image('second.jpg', 150, 150);

        $this->call(
            'PUT',
            '/api/admin/products/' . $product->id,
            [
                'name' => $product->name,
                'sku' => $product->sku,
                'handle' => $product->handle,
            ],
            [],
            [
                'main_image' => $file1,
                'images' => [$file2],
            ],
            [
                'HTTP_Authorization' => 'Bearer ' . $token,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response = $this->getJson('/api/admin/products/' . $product->id, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Product retrieved successfully.');
        $data = $response->json('data');

        $this->assertArrayHasKey('image', $data);
        $this->assertArrayHasKey('image_url', $data);
        $this->assertArrayHasKey('images', $data);
        $this->assertArrayHasKey('primary_image', $data);
        $this->assertNotEmpty($data['image']);
        $this->assertNotEmpty($data['image_url']);

        $images = $data['images'];
        $this->assertCount(2, $images, 'Detail API must return exactly 2 images (no duplication)');

        $paths = [];
        foreach ($images as $img) {
            $this->assertArrayHasKey('image_path', $img);
            $this->assertArrayHasKey('image_url', $img);
            $this->assertNotEmpty($img['image_url'], 'Each image must have full image_url (no truncation)');
            $this->assertStringContainsString('/media/', $img['image_url'] ?? '', 'image_url must be full URL with /media/');
            $path = $img['image_path'] ?? '';
            $this->assertNotContains($path, $paths, 'No duplicate image_path in response');
            $paths[] = $path;
        }

        $this->assertNotNull($data['primary_image']);
        $this->assertSame($data['primary_image']['image_path'] ?? null, $data['image']);
    }
}
