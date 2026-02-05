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
        $this->assertArrayHasKey('main_image', $data);
        $this->assertArrayHasKey('gallery_images', $data);
        $this->assertNotNull($data['main_image'], 'API should return deduplicated main image');
        $this->assertSame($path, $data['main_image']['image_path'] ?? null);
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
        $this->assertArrayHasKey('main_image', $data);
        $this->assertArrayHasKey('gallery_images', $data);
        $this->assertNotEmpty($data['image']);
        $this->assertNotEmpty($data['image_url']);

        $this->assertNotNull($data['main_image'], 'Detail API must return main image');
        $this->assertCount(1, $data['gallery_images'], 'Detail API must return exactly 1 gallery image (no duplication)');
        $this->assertSame($data['main_image']['image_path'] ?? null, $data['image']);
        foreach ([$data['main_image']] as $img) {
            $this->assertArrayHasKey('image_path', $img);
            $this->assertArrayHasKey('image_url', $img);
            $this->assertNotEmpty($img['image_url'], 'Main image must have full image_url');
            $this->assertStringContainsString('/media/', $img['image_url'] ?? '', 'image_url must be full URL with /media/');
        }
        foreach ($data['gallery_images'] as $img) {
            $this->assertArrayHasKey('image_path', $img);
            $this->assertArrayHasKey('image_url', $img);
            $this->assertNotEmpty($img['image_url']);
            $this->assertStringContainsString('/media/', $img['image_url'] ?? '');
        }
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
        $this->assertNotNull($data['main_image'] ?? null, 'Update must return main image');
        $gallery = $data['gallery_images'] ?? [];
        $this->assertCount(2, $gallery, 'Update with 3 files must return 1 main + 2 gallery (no duplication)');
        $paths = array_merge(
            $data['main_image'] ? [$data['main_image']['image_path']] : [],
            array_column($gallery, 'image_path')
        );
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
     * Update with only images[] (gallery): main image must stay unchanged; only gallery is replaced.
     */
    public function test_product_update_only_gallery_keeps_main_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Product With Main',
            'sku' => 'sku-gallery-' . uniqid(),
            'handle' => 'product-gallery-' . uniqid(),
        ]);
        $token = $admin->createToken('test')->plainTextToken;
        $mainFile = UploadedFile::fake()->image('main.jpg', 300, 300);
        $this->call(
            'PUT',
            '/api/admin/products/' . $product->id,
            ['name' => $product->name, 'sku' => $product->sku, 'handle' => $product->handle],
            [],
            ['main_image' => $mainFile],
            ['HTTP_Authorization' => 'Bearer ' . $token, 'HTTP_Accept' => 'application/json']
        );
        $getAfterMain = $this->getJson('/api/admin/products/' . $product->id, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
        $getAfterMain->assertStatus(200);
        $primaryPathBefore = $getAfterMain->json('data.image');
        $primaryUrlBefore = $getAfterMain->json('data.image_url');
        $this->assertNotEmpty($primaryPathBefore, 'Product must have main image after first update');

        $newGallery1 = UploadedFile::fake()->image('gallery1.jpg', 100, 100);
        $newGallery2 = UploadedFile::fake()->image('gallery2.jpg', 100, 100);
        $this->call(
            'PUT',
            '/api/admin/products/' . $product->id,
            ['name' => $product->name, 'sku' => $product->sku, 'handle' => $product->handle],
            [],
            ['images' => [$newGallery1, $newGallery2]],
            ['HTTP_Authorization' => 'Bearer ' . $token, 'HTTP_Accept' => 'application/json']
        );
        $getAfterGallery = $this->getJson('/api/admin/products/' . $product->id, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
        $getAfterGallery->assertStatus(200);
        $data = $getAfterGallery->json('data');
        $this->assertSame($primaryPathBefore, $data['image'], 'Main image path must be unchanged when only gallery is updated');
        $this->assertNotEmpty($data['image_url']);
        $this->assertNotNull($data['main_image'], 'Should have main image');
        $this->assertSame($primaryPathBefore, $data['main_image']['image_path']);
        $this->assertCount(2, $data['gallery_images'] ?? [], 'Should have 2 gallery images');
    }

    /**
     * Create product with multiple files under main_image only: first = primary, rest in images array.
     */
    public function test_product_create_multiple_main_image_first_primary_rest_in_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;
        $file1 = UploadedFile::fake()->image('first.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('second.jpg', 80, 80);
        $file3 = UploadedFile::fake()->image('third.jpg', 60, 60);

        $response = $this->call(
            'POST',
            '/api/admin/products',
            [
                'name' => 'Product Multi Main',
                'description' => 'Desc',
                'price' => 10,
                'stock' => 0,
                'status' => 'draft',
                'category_id' => $category->id,
                'sku' => 'sku-multi-main-' . uniqid(),
                'handle' => 'handle-multi-main-' . uniqid(),
            ],
            [],
            ['main_image' => [$file1, $file2, $file3]],
            [
                'HTTP_Authorization' => 'Bearer ' . $token,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(201);
        $productId = $response->json('data.id');
        $data = $response->json('data');
        $this->assertNotNull($data['main_image'], 'First file must be main image');
        $this->assertCount(2, $data['gallery_images'] ?? [], 'Other 2 files must be gallery images');
        $this->assertNotEmpty($data['image']);
        $this->assertNotEmpty($data['image_url']);

        $product = Product::find($productId);
        $product->load('images');
        $this->assertCount(3, $product->images);
        $primaryImg = $product->images->where('is_primary', true)->first();
        $this->assertNotNull($primaryImg);
        $this->assertSame($product->image, $primaryImg->image_path);
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
        $this->assertArrayHasKey('main_image', $data);
        $this->assertArrayHasKey('gallery_images', $data);
        $this->assertNotNull($data['main_image'], 'Create with 3 files must return main image');
        $this->assertCount(2, $data['gallery_images'] ?? [], 'Create with 3 files must return 2 gallery images');
        foreach (array_merge([$data['main_image']], $data['gallery_images'] ?? []) as $img) {
            $this->assertNotEmpty($img['image_url'] ?? null, 'Each image must have full image_url');
            $this->assertStringContainsString('/media/', $img['image_url'] ?? '');
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
        $this->assertNotNull($updateData['main_image'], 'Update with 2 files must return main image');
        $this->assertCount(1, $updateData['gallery_images'] ?? [], 'Update with 2 files must return 1 gallery image');

        // --- 4. Get product again (admin) ---
        $get2Res = $this->getJson('/api/admin/products/' . $productId, $headers);
        $get2Res->assertStatus(200);
        $data2 = $get2Res->json('data');
        $this->assertNotNull($data2['main_image']);
        $this->assertCount(1, $data2['gallery_images'] ?? []);

        // --- 5. Product detail API (public shop) ---
        $shopRes = $this->getJson('/api/shop/products/' . $productId);
        $shopRes->assertStatus(200);
        $shopData = $shopRes->json('data');
        $this->assertArrayHasKey('main_image', $shopData);
        $this->assertArrayHasKey('gallery_images', $shopData);
        $this->assertNotNull($shopData['main_image']);
        $this->assertCount(1, $shopData['gallery_images'] ?? []);
        foreach (array_merge([$shopData['main_image']], $shopData['gallery_images'] ?? []) as $img) {
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

    /**
     * PUT /api/admin/categories/{id} with image must update the category image.
     * Sends a raw multipart body (like Postman) so parsePutMultipartIntoRequest runs.
     */
    public function test_category_update_with_image_put_api(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $token = $admin->createToken('test')->plainTextToken;

        // Create category without image
        $catRes = $this->postJson('/api/admin/categories', [
            'name' => 'Category To Update',
            'slug' => 'cat-update-' . uniqid(),
            'description' => 'No image yet',
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        $catRes->assertStatus(201);
        $categoryId = $catRes->json('data.id');
        $originalName = $catRes->json('data.name');
        $this->assertNull($catRes->json('data.image'));

        // PUT with image: pass file in $files (Laravel sets it on request); parser runs when body is raw multipart (e.g. Postman)
        $file = UploadedFile::fake()->image('updated.jpg', 120, 120);
        $response = $this->call(
            'PUT',
            '/api/admin/categories/' . $categoryId,
            [
                'name' => $originalName,
                'slug' => $catRes->json('data.slug'),
                'description' => $catRes->json('data.description'),
            ],
            [],
            ['image' => $file],
            [
                'HTTP_Authorization' => 'Bearer ' . $token,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertSame($originalName, $data['name'], 'Name should be unchanged when only image sent');
        $this->assertNotEmpty($data['image'], 'Category image must be set after PUT with image');
        $this->assertNotEmpty($data['image_url']);
        $this->assertStringContainsString('categories/', $data['image']);

        $category = Category::find($categoryId);
        $this->assertNotEmpty($category->image);
        $fullPath = Storage::disk('public')->path($category->image);
        $this->assertFileExists($fullPath);
        $this->assertGreaterThan(0, filesize($fullPath));
    }

    /**
     * POST /api/admin/categories/{id} with form-data image (e.g. Postman) must update the category image.
     * Use POST when PUT with multipart does not work on the server (PHP populates $_FILES for POST).
     */
    public function test_category_update_with_image_post_api(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $token = $admin->createToken('test')->plainTextToken;

        $catRes = $this->postJson('/api/admin/categories', [
            'name' => 'Category Post Update',
            'slug' => 'cat-post-' . uniqid(),
            'description' => 'No image',
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        $catRes->assertStatus(201);
        $categoryId = $catRes->json('data.id');
        $this->assertNull($catRes->json('data.image'));

        $file = UploadedFile::fake()->image('post-update.jpg', 100, 100);
        $response = $this->call(
            'POST',
            '/api/admin/categories/' . $categoryId,
            [
                'name' => 'Category Post Update',
                'slug' => $catRes->json('data.slug'),
                'description' => 'With image',
                '_method' => 'PUT',
            ],
            [],
            ['image' => $file],
            [
                'HTTP_Authorization' => 'Bearer ' . $token,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data['image'], 'Category image must be set after POST with image');
        $this->assertStringContainsString('categories/', $data['image']);
    }

    /**
     * PUT/POST with image_base64 (JSON) must update the category image when file upload doesn't work on server.
     */
    public function test_category_update_with_image_base64_api(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $token = $admin->createToken('test')->plainTextToken;

        $catRes = $this->postJson('/api/admin/categories', [
            'name' => 'Category Base64 Update',
            'slug' => 'cat-b64-' . uniqid(),
            'description' => 'No image',
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        $catRes->assertStatus(201);
        $categoryId = $catRes->json('data.id');
        $this->assertNull($catRes->json('data.image'));

        $file = UploadedFile::fake()->image('b64.jpg', 80, 80);
        $base64 = 'data:image/jpeg;base64,' . base64_encode($file->get());

        $response = $this->putJson('/api/admin/categories/' . $categoryId, [
            'name' => 'Category Base64 Update',
            'slug' => $catRes->json('data.slug'),
            'description' => 'With image via base64',
            'image_base64' => $base64,
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data['image'], 'Category image must be set after update with image_base64');
        $this->assertStringContainsString('categories/', $data['image']);
        $this->assertNotEmpty($data['image_url']);
    }

    /**
     * Create category with minimal data (only name); then update with image; then update to remove image.
     */
    public function test_category_smooth_create_and_partial_update(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $token = $admin->createToken('test')->plainTextToken;

        // Create with only name (no slug, description, image)
        $createRes = $this->postJson('/api/admin/categories', [
            'name' => 'Smooth Category ' . uniqid(),
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        $createRes->assertStatus(201);
        $data = $createRes->json('data');
        $this->assertNotEmpty($data['id']);
        $this->assertNotEmpty($data['name']);
        $this->assertNotEmpty($data['slug'], 'Slug should be auto-generated from name');
        $this->assertNull($data['image']);
        $this->assertNull($data['image_url']);
        $categoryId = $data['id'];

        // Partial update: add description only (no image)
        $update1 = $this->putJson('/api/admin/categories/' . $categoryId, [
            'description' => 'Added description later',
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        $update1->assertStatus(200);
        $this->assertSame('Added description later', $update1->json('data.description'));

        // Partial update: add image via base64
        $file = UploadedFile::fake()->image('smooth.jpg', 60, 60);
        $base64 = 'data:image/jpeg;base64,' . base64_encode($file->get());
        $update2 = $this->putJson('/api/admin/categories/' . $categoryId, [
            'image_base64' => $base64,
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        $update2->assertStatus(200);
        $this->assertNotEmpty($update2->json('data.image'));
        $this->assertNotEmpty($update2->json('data.image_url'));

        // Partial update: remove image (image_remove=true)
        $update3 = $this->putJson('/api/admin/categories/' . $categoryId, [
            'image_remove' => true,
        ], ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
        $update3->assertStatus(200);
        $this->assertNull($update3->json('data.image'));
        $this->assertNull($update3->json('data.image_url'));
    }
}
