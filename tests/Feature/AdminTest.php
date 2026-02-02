<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('data.price', 99.99);

        $this->assertDatabaseHas('products', [
            'name'   => $payload['name'],
            'sku'    => $payload['sku'],
            'handle' => $unique,
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
            ->assertJsonPath('data.image', $imageUrl1);

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
     * Test admin can show single product
     */
    public function test_admin_can_show_product()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $product = $this->createProduct(['name' => 'Show Me']);

        $response = $this->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Show Me');
    }

    /**
     * Test admin can update product
     */
    public function test_admin_can_update_product()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        $product = $this->createProduct(['name' => 'Original Name']);

        $response = $this->putJson("/api/admin/products/{$product->id}", [
            'name'  => 'Updated Name',
            'price' => 199.99,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.price', 199.99);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Name']);
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
}

