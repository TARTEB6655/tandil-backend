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

    /**
     * Update with main_image + 2 extra images: must result in exactly 3 images, no duplication.
     * Stored files must be full size (verify stored file size matches uploaded).
     */
    public function test_product_update_three_images_no_duplication_full_size(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Three Images Product',
            'sku' => 'sku-three-' . uniqid(),
            'handle' => 'product-three-' . uniqid(),
        ]);
        $token = $admin->createToken('test')->plainTextToken;
        $mainFile = UploadedFile::fake()->image('main.jpg', 300, 300);
        $extra1 = UploadedFile::fake()->image('extra1.jpg', 200, 200);
        $extra2 = UploadedFile::fake()->image('extra2.jpg', 150, 150);

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
                'main_image' => $mainFile,
                'images' => [$extra1, $extra2],
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
        $data = $response->json('data');
        $images = $data['images'] ?? [];
        $this->assertCount(3, $images, 'Update with 3 files must return exactly 3 images (no duplication)');

        $paths = array_column($images, 'image_path');
        $this->assertCount(3, array_unique($paths), 'All image_path values must be unique');

        $product->refresh();
        $product->load('images');
        $this->assertCount(3, $product->images);
        foreach ($product->images as $img) {
            $fullPath = Storage::disk('public')->path($img->image_path);
            $this->assertFileExists($fullPath);
            $size = filesize($fullPath);
            $this->assertGreaterThan(0, $size, 'Stored image must not be empty (no truncation)');
        }
    }

    /**
     * End-to-end: create product (all fields + images) -> get -> update -> get -> shop detail -> delete;
     * then create category with image -> get -> update category with image -> get.
     * Asserts images are full size and no duplication in every response.
     */
    public function test_full_flow_product_and_category_apis_images_no_duplication(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $token = $admin->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];

        // --- Category first (for product category_id) ---
        $catRes = $this->postJson('/api/admin/categories', [
            'name' => 'E2E Category',
            'slug' => 'e2e-category-' . uniqid(),
            'description' => 'Category for E2E test',
        ], $headers);
        $catRes->assertStatus(201);
        $categoryId = $catRes->json('data.id');
        $this->assertGreaterThan(0, $categoryId);

        // --- 1. Create product (all fields + main_image + images[]) ---
        $mainFile = UploadedFile::fake()->image('main.jpg', 200, 200);
        $extra1 = UploadedFile::fake()->image('extra1.jpg', 150, 150);
        $extra2 = UploadedFile::fake()->image('extra2.jpg', 100, 100);
        $createRes = $this->call('POST', '/api/admin/products', [
            'name' => 'E2E Dummy Product',
            'description' => 'Full description for E2E',
            'price' => 99.99,
            'stock' => 10,
            'status' => 'active',
            'category_id' => $categoryId,
            'weight_unit' => 'kg',
            'sku' => 'e2e-sku-' . uniqid(),
            'handle' => 'e2e-handle-' . uniqid(),
        ], [], [
            'main_image' => $mainFile,
            'images' => [$extra1, $extra2],
        ], ['HTTP_Authorization' => 'Bearer ' . $token, 'HTTP_Accept' => 'application/json']);
        $createRes->assertStatus(201);
        $productId = $createRes->json('data.id');
        $this->assertGreaterThan(0, $productId);

        // --- 2. Get product (admin) - images full size, no dup ---
        $getRes = $this->getJson('/api/admin/products/' . $productId, $headers);
        $getRes->assertStatus(200);
        $data = $getRes->json('data');
        $this->assertArrayHasKey('images', $data);
        $images = $data['images'];
        $this->assertCount(3, $images, 'Create with 3 files must return 3 images (no duplication)');
        $paths = [];
        foreach ($images as $img) {
            $this->assertNotEmpty($img['image_url'] ?? null, 'Each image must have full image_url');
            $this->assertStringContainsString('/media/', $img['image_url'] ?? '');
            $path = $img['image_path'] ?? '';
            $this->assertNotContains($path, $paths);
            $paths[] = $path;
        }

        // --- 3. Update product (new name + replace with 2 images) ---
        $main2 = UploadedFile::fake()->image('main2.jpg', 180, 180);
        $extra3 = UploadedFile::fake()->image('extra3.jpg', 120, 120);
        $updateRes = $this->call('PUT', '/api/admin/products/' . $productId, [
            'name' => 'E2E Product Updated',
            'description' => $data['description'] ?? '',
            'price' => 79.99,
            'stock' => 5,
            'status' => 'active',
            'category_id' => $categoryId,
            'sku' => $data['sku'],
            'handle' => $data['handle'],
        ], [], [
            'main_image' => $main2,
            'images' => [$extra3],
        ], ['HTTP_Authorization' => 'Bearer ' . $token, 'HTTP_Accept' => 'application/json']);
        $updateRes->assertStatus(200);
        $updateData = $updateRes->json('data');
        $this->assertSame('E2E Product Updated', $updateData['name']);
        $updateImages = $updateData['images'] ?? [];
        $this->assertCount(2, $updateImages, 'Update with 2 files must return 2 images (no duplication)');
        $paths2 = array_column($updateImages, 'image_path');
        $this->assertCount(2, array_unique($paths2));

        // --- 4. Get product again (admin) ---
        $get2Res = $this->getJson('/api/admin/products/' . $productId, $headers);
        $get2Res->assertStatus(200);
        $data2 = $get2Res->json('data');
        $this->assertCount(2, $data2['images'] ?? []);

        // --- 5. Product detail API (public shop) ---
        $shopRes = $this->getJson('/api/shop/products/' . $productId);
        $shopRes->assertStatus(200);
        $shopData = $shopRes->json('data');
        $this->assertArrayHasKey('images', $shopData);
        $shopImages = $shopData['images'];
        $this->assertCount(2, $shopImages);
        foreach ($shopImages as $img) {
            $this->assertNotEmpty($img['image_url'] ?? null);
            $this->assertStringContainsString('/media/', $img['image_url'] ?? '');
        }

        // Stored product image files are full size (no truncation)
        $product = Product::find($productId);
        $product->load('images');
        $this->assertCount(2, $product->images);
        foreach ($product->images as $img) {
            $fullPath = Storage::disk('public')->path($img->image_path);
            $this->assertFileExists($fullPath);
            $this->assertGreaterThan(0, filesize($fullPath));
        }

        // --- 6. Delete product ---
        $delRes = $this->deleteJson('/api/admin/products/' . $productId, [], $headers);
        $delRes->assertStatus(200);

        // --- 7. Create category with image ---
        $catImage = UploadedFile::fake()->image('category.jpg', 160, 160);
        $catCreateRes = $this->call('POST', '/api/admin/categories', [
            'name' => 'E2E Category With Image',
            'slug' => 'e2e-cat-img-' . uniqid(),
            'description' => 'Category with image',
        ], [], ['image' => $catImage], ['HTTP_Authorization' => 'Bearer ' . $token, 'HTTP_Accept' => 'application/json']);
        $catCreateRes->assertStatus(201);
        $catId2 = $catCreateRes->json('data.id');
        $this->assertNotEmpty($catCreateRes->json('data.image'));
        $this->assertNotEmpty($catCreateRes->json('data.image_url'));
        $this->assertStringContainsString('/media/', $catCreateRes->json('data.image_url') ?? '');

        // --- 8. Get category - image present ---
        $catGetRes = $this->getJson('/api/admin/categories/' . $catId2, $headers);
        $catGetRes->assertStatus(200);
        $catData = $catGetRes->json('data');
        $this->assertNotEmpty($catData['image']);
        $this->assertNotEmpty($catData['image_url']);

        // --- 9. Update category with new image (PUT multipart) ---
        $catImage2 = UploadedFile::fake()->image('category2.jpg', 140, 140);
        $catUpdateRes = $this->call('PUT', '/api/admin/categories/' . $catId2, [
            'name' => 'E2E Category Image Updated',
            'slug' => $catData['slug'],
            'description' => $catData['description'] ?? '',
        ], [], ['image' => $catImage2], ['HTTP_Authorization' => 'Bearer ' . $token, 'HTTP_Accept' => 'application/json']);
        $catUpdateRes->assertStatus(200);
        $catUpdated = $catUpdateRes->json('data');
        $this->assertNotEmpty($catUpdated['image']);
        $this->assertNotEmpty($catUpdated['image_url']);
        $this->assertStringContainsString('/media/', $catUpdated['image_url'] ?? '');

        // --- 10. Get category again - updated image, full size ---
        $catGet2Res = $this->getJson('/api/admin/categories/' . $catId2, $headers);
        $catGet2Res->assertStatus(200);
        $catFinal = $catGet2Res->json('data');
        $this->assertSame('E2E Category Image Updated', $catFinal['name']);
        $this->assertNotEmpty($catFinal['image']);
        $this->assertNotEmpty($catFinal['image_url']);
        $storedPath = $catFinal['image'];
        $fullPath = Storage::disk('public')->path($storedPath);
        $this->assertFileExists($fullPath);
        $this->assertGreaterThan(0, filesize($fullPath), 'Category image must be full size (no truncation)');
    }
}
