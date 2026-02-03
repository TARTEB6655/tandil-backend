<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can list users
     */
    public function test_admin_can_list_users()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->createCustomer();
        $this->createTechnician();

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    /**
     * Test admin can create user
     */
    public function test_admin_can_create_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
            'status' => 'active',
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    /**
     * Test admin can view user
     */
    public function test_admin_can_view_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $user = $this->createCustomer();

        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200);
    }

    /**
     * Test admin can update user
     */
    public function test_admin_can_update_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $user = $this->createCustomer();

        $response = $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'client',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test admin can delete user
     */
    public function test_admin_can_delete_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $user = $this->createCustomer();

        $response = $this->deleteJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * Test admin can list roles
     */
    public function test_admin_can_list_roles()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200);
    }

    /**
     * Test admin can create role
     */
    public function test_admin_can_create_role()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'custom_role',
            'permissions' => [],
        ]);

        $response->assertStatus(201) // JSON response for API
            ->assertJson(['status' => true]);
    }

    /**
     * Test unauthorized access to admin routes
     */
    public function test_non_admin_cannot_access_admin_routes()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Admin Product APIs (ensure no 500 on duplicate handle/SKU; proper 422)
    // -------------------------------------------------------------------------

    /**
     * Test admin can list products
     */
    public function test_admin_can_list_products()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $this->createProduct();
        $this->createProduct();

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data', 'pagination']);
    }

    /**
     * Test admin can list products filtered by category_id
     */
    public function test_admin_can_list_products_filter_by_category_id()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $catA = Category::factory()->create(['name' => 'Category A']);
        $catB = Category::factory()->create(['name' => 'Category B']);
        $this->createProduct(['name' => 'Product In A', 'category_id' => $catA->id]);
        $this->createProduct(['name' => 'Product In B', 'category_id' => $catB->id]);
        $this->createProduct(['name' => 'Another In A', 'category_id' => $catA->id]);

        $response = $this->getJson('/api/admin/products?category_id=' . $catA->id);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('pagination.total', 2);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $product) {
            $this->assertSame($catA->id, $product['category_id']);
            $this->assertArrayHasKey('category', $product);
            $this->assertSame($catA->id, $product['category']['id']);
        }
    }

    /**
     * Test admin can create product (valid payload)
     */
    public function test_admin_can_create_product()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $category = Category::factory()->create();
        $unique = 'test-product-' . uniqid();
        $payload = [
            'name'             => 'Test Product ' . $unique,
            'description'      => 'Test description',
            'handle'           => $unique,
            'sku'              => 'SKU-TEST-' . uniqid(),
            'barcode'          => '1234567890123',
            'price'            => 99.99,
            'compare_at_price' => 129.99,
            'cost_per_item'    => 50,
            'stock'            => 100,
            'status'           => 'active',
            'track_quantity'   => true,
            'allow_backorder'  => false,
            'weight'           => '1',
            'weight_unit'      => 'kg',
            'tags'             => 'test, sample',
            'meta_title'       => 'Test Product Meta',
            'meta_description' => 'Test meta description',
            'category_id'      => $category->id,
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.price', 99.99)
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', $category->name);

        $this->assertDatabaseHas('products', [
            'name'        => $payload['name'],
            'sku'         => $payload['sku'],
            'handle'      => $unique,
            'category_id' => $category->id,
        ]);
    }

    /**
     * Test admin can create product with image_urls (JSON API image parameter)
     */
    public function test_admin_can_create_product_with_image_urls()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $category = Category::factory()->create();
        $unique = 'test-product-img-' . uniqid();
        $imageUrl1 = 'https://example.com/product1.jpg';
        $imageUrl2 = 'https://example.com/product2.jpg';

        $payload = [
            'name'             => 'Product With Images ' . $unique,
            'description'      => 'Has image_urls',
            'handle'           => $unique,
            'sku'              => 'SKU-IMG-' . uniqid(),
            'price'            => 49.99,
            'status'           => 'active',
            'track_quantity'   => true,
            'allow_backorder'  => false,
            'weight_unit'      => 'kg',
            'category_id'      => $category->id,
            'image_urls'       => [$imageUrl1, $imageUrl2],
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.image', $imageUrl1)
            ->assertJsonPath('data.image_url', $imageUrl1);

        $product = \App\Models\Product::where('handle', $unique)->first();
        $this->assertNotNull($product);
        $this->assertSame($imageUrl1, $product->image);
        $this->assertSame(2, $product->images()->count());
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'image_path' => $imageUrl1,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'image_path' => $imageUrl2,
        ]);
    }

    /**
     * Test add product API returns correct image_url when image file is uploaded.
     */
    public function test_add_product_with_image_file_returns_image_url()
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required for image fake.');
        }
        Storage::fake('public');

        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create();
        $unique = 'test-product-file-' . uniqid();
        $file = UploadedFile::fake()->image('product.jpg', 100, 100);

        $response = $this->post('/api/admin/products', [
            'name'        => 'Product With File ' . $unique,
            'description' => 'Has image file',
            'handle'      => $unique,
            'sku'         => 'SKU-FILE-' . uniqid(),
            'price'       => 29.99,
            'status'      => 'active',
            'category_id' => (string) $category->id,
            'image'       => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Product With File ' . $unique);

        $data = $response->json('data');
        $this->assertArrayHasKey('image_url', $data);
        $imageUrl = $data['image_url'];
        $this->assertNotNull($imageUrl);
        $this->assertStringContainsString('/media/products/', $imageUrl);
        $this->assertStringEndsWith('.jpg', $imageUrl);

        $product = Product::where('handle', $unique)->first();
        $this->assertNotNull($product->image);
        $this->assertStringContainsString('products/', $product->image);
    }

    /**
     * Test admin can create product without category_id (category optional)
     */
    public function test_admin_can_create_product_without_category_id()
    {
        $driver = \Illuminate\Support\Facades\Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $this->markTestSkipped('SQLite keeps category_id NOT NULL in this migration; use MySQL/PostgreSQL for optional category.');
        }

        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $unique = 'no-cat-' . uniqid();
        $payload = [
            'name'             => 'Product Without Category ' . $unique,
            'description'      => 'No category at creation',
            'handle'           => $unique,
            'sku'              => 'SKU-NOCAT-' . uniqid(),
            'price'            => 29.99,
            'status'           => 'active',
            'track_quantity'   => true,
            'allow_backorder'  => false,
            'weight_unit'      => 'kg',
        ];

        $response = $this->postJson('/api/admin/products', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.price', 29.99);

        $this->assertDatabaseHas('products', [
            'name'        => $payload['name'],
            'handle'      => $unique,
            'category_id' => null,
        ]);
    }

    /**
     * Test duplicate handle returns 422 (not 500)
     */
    public function test_admin_product_duplicate_handle_returns_422()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $existing = $this->createProduct([
            'name'   => 'Existing Product',
            'handle' => 'existing-product-handle',
            'sku'    => 'SKU-EXISTING-' . uniqid(),
        ]);

        $response = $this->postJson('/api/admin/products', [
            'name'             => 'New Product',
            'handle'           => 'existing-product-handle',
            'sku'              => 'SKU-NEW-' . uniqid(),
            'price'            => 50,
            'status'           => 'active',
            'track_quantity'   => true,
            'allow_backorder'  => false,
            'weight_unit'      => 'kg',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.handle.0', 'The handle has already been taken. Please use a different handle or leave it blank to auto-generate.');
    }

    /**
     * Test duplicate SKU returns 422 (not 500)
     */
    public function test_admin_product_duplicate_sku_returns_422()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->createProduct([
            'name' => 'Existing Product',
            'sku'  => 'SKU-DUP-001',
        ]);

        $response = $this->postJson('/api/admin/products', [
            'name'             => 'Another Product',
            'sku'              => 'SKU-DUP-001',
            'price'            => 50,
            'status'           => 'active',
            'track_quantity'   => true,
            'allow_backorder'  => false,
            'weight_unit'      => 'kg',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.sku.0', 'The SKU has already been taken. Please use a unique SKU.');
    }

    /**
     * Test admin can show single product (includes category)
     */
    public function test_admin_can_show_product()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'Electronics']);
        $product = $this->createProduct(['name' => 'Show Me', 'category_id' => $category->id]);

        $response = $this->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Show Me')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', 'Electronics');
    }

    /**
     * Test admin can update product (including category_id)
     */
    public function test_admin_can_update_product()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'Updated Category']);
        $product = $this->createProduct(['name' => 'Original Name']);

        $response = $this->putJson("/api/admin/products/{$product->id}", [
            'name'        => 'Updated Name',
            'price'       => 199.99,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.price', 199.99)
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', 'Updated Category');

        $this->assertDatabaseHas('products', [
            'id'          => $product->id,
            'name'        => 'Updated Name',
            'category_id' => $category->id,
        ]);
    }

    /**
     * Test admin can delete product
     */
    public function test_admin_can_delete_product()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $product = $this->createProduct();

        $response = $this->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * Test admin can bulk delete products
     */
    public function test_admin_can_bulk_delete_products()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $p1 = $this->createProduct(['name' => 'Bulk Delete 1']);
        $p2 = $this->createProduct(['name' => 'Bulk Delete 2']);

        $response = $this->postJson('/api/admin/products/bulk-delete', [
            'product_ids' => [$p1->id, $p2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('count', 2);
        $this->assertDatabaseMissing('products', ['id' => $p1->id]);
        $this->assertDatabaseMissing('products', ['id' => $p2->id]);
    }

    /**
     * Test admin can bulk update product status
     */
    public function test_admin_can_bulk_update_product_status()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $p1 = $this->createProduct(['name' => 'Bulk Status 1', 'status' => 'draft']);
        $p2 = $this->createProduct(['name' => 'Bulk Status 2', 'status' => 'draft']);

        $response = $this->postJson('/api/admin/products/bulk-update-status', [
            'product_ids' => [$p1->id, $p2->id],
            'status'      => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('count', 2);
        $this->assertDatabaseHas('products', ['id' => $p1->id, 'status' => 'active']);
        $this->assertDatabaseHas('products', ['id' => $p2->id, 'status' => 'active']);
    }

    /**
     * Test admin can bulk update product stock
     */
    public function test_admin_can_bulk_update_product_stock()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $p1 = $this->createProduct(['name' => 'Bulk Stock 1', 'stock' => 0]);
        $p2 = $this->createProduct(['name' => 'Bulk Stock 2', 'stock' => 0]);

        $response = $this->postJson('/api/admin/products/bulk-update-stock', [
            'product_ids' => [$p1->id, $p2->id],
            'stock'       => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('count', 2);
        $this->assertDatabaseHas('products', ['id' => $p1->id, 'stock' => 50]);
        $this->assertDatabaseHas('products', ['id' => $p2->id, 'stock' => 50]);
    }

    /**
     * Test admin can toggle product status
     */
    public function test_admin_can_toggle_product_status()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $product = $this->createProduct(['name' => 'Toggle Me', 'status' => 'draft']);

        $response = $this->postJson("/api/admin/products/{$product->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
    }

    /**
     * Test admin can list categories (GET /api/admin/categories)
     */
    public function test_admin_can_list_categories()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        Category::factory()->create(['name' => 'Cat A', 'slug' => 'cat-a']);
        Category::factory()->create(['name' => 'Cat B', 'slug' => 'cat-b']);

        $response = $this->getJson('/api/admin/categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data' => [['id', 'name', 'slug']]]]);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data['data'] ?? []));
    }

    /**
     * Test admin can add category with JSON (POST /api/admin/categories)
     */
    public function test_admin_can_add_category()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/categories', [
            'name' => 'New Category',
            'description' => 'Description here',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Category')
            ->assertJsonPath('data.slug', 'new-category')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description']]);
        $this->assertDatabaseHas('categories', ['name' => 'New Category', 'slug' => 'new-category']);
    }

    /**
     * Test admin can create category with multipart/form-data (POST /api/admin/categories) including image
     */
    public function test_admin_can_create_category_with_multipart()
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required for image fake.');
        }
        Storage::fake('public');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('category-multipart.jpg', 100, 100);

        $response = $this->post('/api/admin/categories', [
            'name'        => 'Multipart Category',
            'slug'        => 'multipart-category',
            'description' => 'Created via form-data',
            'image'       => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Multipart Category')
            ->assertJsonPath('data.slug', 'multipart-category')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description', 'image', 'image_url']]);

        $data = $response->json('data');
        $this->assertNotNull($data['image']);
        $this->assertStringContainsString('categories/', $data['image']);
        $this->assertNotNull($data['image_url']);
        $this->assertStringContainsString('/media/categories/', $data['image_url']);

        $category = Category::where('slug', 'multipart-category')->first();
        $this->assertNotNull($category);
        $this->assertNotNull($category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    /**
     * Test admin can get category (GET /api/admin/categories/{id})
     */
    public function test_admin_can_get_category()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'Get Me', 'slug' => 'get-me']);

        $response = $this->getJson('/api/admin/categories/' . $category->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Get Me');
    }

    /**
     * Test admin can update category with JSON (PUT /api/admin/categories/{id})
     */
    public function test_admin_can_update_category()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'Original', 'slug' => 'original']);

        $response = $this->putJson('/api/admin/categories/' . $category->id, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description', 'image', 'image_url']]);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Name']);
    }

    /**
     * Test admin can update category with form-data only (PUT, no image) - mirrors Postman form-data update.
     * Ensures name and slug from request are applied and returned in response.
     */
    public function test_admin_can_update_category_with_form_data_only()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'Fertilizers', 'slug' => 'fertilizers', 'description' => 'Organic and chemical fertilizers']);

        $response = $this->call('PUT', '/api/admin/categories/' . $category->id, [
            'name' => 'test category',
            'slug' => 'updated-slug',
        ], [], [], [
            'HTTP_Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Category updated successfully.')
            ->assertJsonPath('data.name', 'test category')
            ->assertJsonPath('data.slug', 'updated-slug');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'test category', 'slug' => 'updated-slug']);

        $category->refresh();
        $this->assertSame('test category', $category->name);
        $this->assertSame('updated-slug', $category->slug);
    }

    /**
     * Test admin can update category with multipart/form-data (PUT /api/admin/categories/{id}) including new image
     */
    public function test_admin_can_update_category_with_multipart()
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required for image fake.');
        }
        Storage::fake('public');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $category = Category::factory()->create([
            'name'  => 'Original',
            'slug'  => 'original',
            'image' => 'categories/old-cat.jpg',
        ]);
        Storage::disk('public')->put('categories/old-cat.jpg', 'fake');

        $file = UploadedFile::fake()->image('updated-category.jpg', 100, 100);

        $response = $this->call('PUT', '/api/admin/categories/' . $category->id, [
            'name'        => 'Updated Via Multipart',
            'slug'        => 'updated-via-multipart',
            'description' => 'Updated with form-data and image',
            'image'       => $file,
        ], [], [], [
            'HTTP_Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Via Multipart')
            ->assertJsonPath('data.slug', 'updated-via-multipart');

        $data = $response->json('data');
        $this->assertNotNull($data['image']);
        $this->assertStringContainsString('categories/', $data['image']);
        $this->assertNotNull($data['image_url']);
        $this->assertStringContainsString('/media/categories/', $data['image_url']);

        $category->refresh();
        $this->assertSame('Updated Via Multipart', $category->name);
        $this->assertNotSame('categories/old-cat.jpg', $category->image);
        Storage::disk('public')->assertMissing('categories/old-cat.jpg');
        Storage::disk('public')->assertExists($category->image);
    }

    /**
     * Test admin can delete category (DELETE /api/admin/categories/{id})
     */
    public function test_admin_can_delete_category()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'To Delete', 'slug' => 'to-delete']);
        $id = $category->id;

        $response = $this->deleteJson('/api/admin/categories/' . $id);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    /**
     * Test admin can update product with multipart form-data (PUT /api/admin/products/{id})
     */
    public function test_admin_can_update_product_with_multipart()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'Cat', 'slug' => 'cat']);
        $product = $this->createProduct(['name' => 'Original', 'category_id' => $category->id]);

        $response = $this->call('PUT', '/api/admin/products/' . $product->id, [
            'name' => 'Updated via multipart',
            'price' => '79.99',
            'stock' => '15',
            'category_id' => (string) $category->id,
        ], [], [], [
            'HTTP_Accept' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Updated via multipart')
            ->assertJsonPath('data.category_id', $category->id);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated via multipart',
        ]);
    }

    /**
     * Test admin can update product with new image (PUT multipart) and all fields are applied.
     */
    public function test_admin_can_update_product_with_image_and_all_fields()
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $category = Category::factory()->create(['name' => 'Cat', 'slug' => 'cat']);
        $product = $this->createProduct([
            'name' => 'Original',
            'price' => 10,
            'category_id' => $category->id,
            'sku' => 'SKU-ORIG-' . uniqid(),
        ]);
        $file = UploadedFile::fake()->image('updated-product.jpg', 100, 100);

        $response = $this->call('PUT', '/api/admin/products/' . $product->id, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'price' => '99.99',
            'stock' => '25',
            'status' => 'active',
            'category_id' => (string) $category->id,
            'weight_unit' => 'kg',
            'sku' => 'SKU-UPD-' . uniqid(),
        ], [], [
            'image' => $file,
        ], [
            'HTTP_Accept' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.price', 99.99)
            ->assertJsonPath('data.stock', 25)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.weight_unit', 'kg');

        $data = $response->json('data');
        $this->assertArrayHasKey('image_url', $data);
        $this->assertNotNull($data['image_url']);
        $this->assertStringContainsString('/media/products/', $data['image_url']);
        $this->assertStringContainsString('.jpg', $data['image_url']);

        $product->refresh();
        $this->assertSame('Updated Name', $product->name);
        $this->assertSame(99.99, (float) $product->price);
        $this->assertSame(25, $product->stock);
        $this->assertNotNull($product->image);
        $this->assertStringContainsString('products/', $product->image);
    }
}

