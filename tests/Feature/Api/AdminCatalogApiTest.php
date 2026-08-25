<?php

namespace Tests\Feature\Api;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function authJson(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ], $headers);
    }

    /**
     * @return array{vendor: Vendor}
     */
    private function seedFulfillmentVendor(): array
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Catalog Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        return ['vendor' => $vendor];
    }

    private function categoryPayload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Test Category',
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ], $extra);
    }

    // ---- Categories ----
    public function test_admin_categories_list_returns_success(): void
    {
        Category::factory()->count(2)->create();
        $response = $this->getJson('/api/admin/categories', $this->authJson());
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data', 'pagination']);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_categories_create_and_show(): void
    {
        $response = $this->postJson('/api/admin/categories', $this->categoryPayload([
            'name' => 'Test Category',
        ]), $this->authJson());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $id = $response->json('data.id');
        $this->assertNotNull($id);

        $show = $this->getJson("/api/admin/categories/{$id}", $this->authJson());
        $show->assertStatus(200);
        $show->assertJsonPath('data.name', 'Test Category');
    }

    public function test_admin_categories_create_with_is_active_and_toggle_status(): void
    {
        $response = $this->postJson('/api/admin/categories', $this->categoryPayload([
            'name' => 'Disabled Category',
            'is_active' => false,
        ]), $this->authJson());
        $response->assertStatus(201);
        $id = $response->json('data.id');
        $show = $this->getJson("/api/admin/categories/{$id}", $this->authJson());
        $show->assertStatus(200);
        $show->assertJsonPath('data.is_active', false);

        $toggle = $this->postJson("/api/admin/categories/{$id}/toggle-status", [], $this->authJson());
        $toggle->assertStatus(200);
        $toggle->assertJsonPath('data.is_active', true);
        $toggle->assertJsonPath('data.id', $id);
    }

    public function test_admin_categories_update_and_delete(): void
    {
        $cat = Category::factory()->create(['name' => 'Original']);
        $response = $this->putJson("/api/admin/categories/{$cat->id}", [
            'name' => 'Updated Category',
        ], $this->authJson());
        $response->assertStatus(200);
        $cat->refresh();
        $this->assertSame('Updated Category', $cat->name);

        $del = $this->deleteJson("/api/admin/categories/{$cat->id}", [], $this->authJson());
        $del->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
    }

    // ---- Services ----
    public function test_admin_services_list_returns_success(): void
    {
        Service::factory()->count(2)->create();
        $response = $this->getJson('/api/admin/services', $this->authJson());
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data', 'pagination']);
    }

    public function test_admin_services_create_and_show(): void
    {
        $response = $this->postJson('/api/admin/services', [
            'name' => 'Test Service',
        ], $this->authJson());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $id = $response->json('data.id');
        $this->assertNotNull($id);

        $show = $this->getJson("/api/admin/services/{$id}", $this->authJson());
        $show->assertStatus(200);
        $show->assertJsonPath('data.name', 'Test Service');
    }

    public function test_admin_services_update_and_delete(): void
    {
        $svc = Service::factory()->create(['name' => 'Original Service']);
        $response = $this->putJson("/api/admin/services/{$svc->id}", [
            'name' => 'Updated Service',
        ], $this->authJson());
        $response->assertStatus(200);
        $svc->refresh();
        $this->assertSame('Updated Service', $svc->name);

        $del = $this->deleteJson("/api/admin/services/{$svc->id}", [], $this->authJson());
        $del->assertStatus(200);
        $this->assertDatabaseMissing('services', ['id' => $svc->id]);
    }

    public function test_admin_services_toggle_status(): void
    {
        $svc = Service::factory()->create(['name' => 'Toggle Service', 'is_active' => true]);
        $response = $this->postJson("/api/admin/services/{$svc->id}/toggle-status", [], $this->authJson());
        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
        $response->assertJsonPath('data.id', $svc->id);

        $response2 = $this->postJson("/api/admin/services/{$svc->id}/toggle-status", [], $this->authJson());
        $response2->assertStatus(200);
        $response2->assertJsonPath('data.is_active', true);
    }

    // ---- Products ----
    public function test_admin_products_list_returns_success(): void
    {
        Category::factory()->create(); // ensure category exists for product factory (SQLite NOT NULL)
        Product::factory()->count(2)->create();
        $response = $this->getJson('/api/admin/products', $this->authJson());
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data', 'pagination']);
        $response->assertJsonPath('status', true);
    }

    public function test_admin_products_create_and_show(): void
    {
        ['vendor' => $vendor] = $this->seedFulfillmentVendor();
        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Test Product',
            'price' => 10.50,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'product',
        ], $this->authJson());
        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $id = $response->json('data.id');
        $this->assertNotNull($id);

        $show = $this->getJson("/api/admin/products/{$id}", $this->authJson());
        $show->assertStatus(200);
        $show->assertJsonPath('data.name', 'Test Product');
    }

    public function test_admin_products_create_with_estimated_arrival_and_job_duration(): void
    {
        $category = Category::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Service SKU',
            'price' => 99,
            'category_id' => $category->id,
            'service_id' => $service->id,
            'type' => 'service',
            'estimated_arrival' => 'Within 2 hours',
            'job_duration' => '45 minutes',
        ], $this->authJson());
        $response->assertStatus(201);
        $response->assertJsonPath('data.estimated_arrival', 'Within 2 hours');
        $response->assertJsonPath('data.job_duration', '45 minutes');
        $id = $response->json('data.id');
        $this->assertDatabaseHas('products', [
            'id' => $id,
            'estimated_arrival' => 'Within 2 hours',
            'job_duration' => '45 minutes',
        ]);
    }

    public function test_admin_products_update_and_delete(): void
    {
        ['vendor' => $vendor] = $this->seedFulfillmentVendor();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Original Product',
            'price' => 5,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'product',
        ]);
        $response = $this->putJson("/api/admin/products/{$product->id}", [
            'name' => 'Updated Product',
            'price' => 15,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'type' => 'product',
        ], $this->authJson());
        $response->assertStatus(200);
        $product->refresh();
        $this->assertSame('Updated Product', $product->name);
        $this->assertSame(15.0, (float) $product->price);

        $del = $this->deleteJson("/api/admin/products/{$product->id}", [], $this->authJson());
        $del->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_admin_products_create_with_service_ids_and_get_returns_service_ids(): void
    {
        $category = Category::factory()->create();
        $service1 = Service::factory()->create(['name' => 'Service One']);
        $service2 = Service::factory()->create(['name' => 'Service Two']);

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Product With Services',
            'price' => 25.00,
            'category_id' => $category->id,
            'service_ids' => [$service1->id, $service2->id],
        ], $this->authJson());
        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertNotNull($id);

        $show = $this->getJson("/api/admin/products/{$id}", $this->authJson());
        $show->assertStatus(200);
        $show->assertJsonPath('data.name', 'Product With Services');
        $this->assertSame('service', Product::find($id)?->type);
        $serviceIds = $show->json('data.service_ids');
        $this->assertIsArray($serviceIds);
        $this->assertCount(2, $serviceIds);
        $this->assertContains($service1->id, $serviceIds);
        $this->assertContains($service2->id, $serviceIds);
    }

    public function test_admin_products_create_with_single_service_id_and_update(): void
    {
        $category = Category::factory()->create();
        $service = Service::factory()->create(['name' => 'Single Service']);

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Product With One Service',
            'price' => 19.99,
            'category_id' => $category->id,
            'service_id' => $service->id,
        ], $this->authJson());
        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertNotNull($id);

        $show = $this->getJson("/api/admin/products/{$id}", $this->authJson());
        $show->assertStatus(200);
        $show->assertJsonPath('data.name', 'Product With One Service');
        $serviceIds = $show->json('data.service_ids');
        $this->assertIsArray($serviceIds);
        $this->assertCount(1, $serviceIds);
        $this->assertContains($service->id, $serviceIds);

        $response2 = $this->putJson("/api/admin/products/{$id}", [
            'name' => 'Product With One Service',
            'price' => 19.99,
            'service_id' => $service->id,
        ], $this->authJson());
        $response2->assertStatus(200);
    }

    public function test_admin_products_create_with_is_featured_and_public_featured_api(): void
    {
        ['vendor' => $vendor] = $this->seedFulfillmentVendor();
        $category = Category::factory()->create();
        $response = $this->postJson('/api/admin/products', [
            'name' => 'Featured Product',
            'price' => 29.99,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'product',
            'status' => 'active',
            'is_featured' => true,
        ], $this->authJson());
        $response->assertStatus(201);
        $id = $response->json('data.id');
        $this->assertNotNull($id);
        $response->assertJsonPath('data.is_featured', true);

        $featured = $this->getJson('/api/shop/products/featured?limit=10', ['Accept' => 'application/json']);
        $featured->assertStatus(200);
        $featured->assertJsonPath('success', true);
        $featured->assertJsonStructure(['data']);
        $data = $featured->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
        $first = $data[0];
        $this->assertSame('Featured Product', $first['name']);
        $this->assertTrue($first['is_featured']);
    }

    public function test_public_featured_products_returns_only_active_and_featured(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['name' => 'Draft Product', 'status' => 'draft', 'is_featured' => true, 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Not Featured', 'status' => 'active', 'is_featured' => false, 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Featured One', 'status' => 'active', 'is_featured' => true, 'category_id' => $category->id]);

        $response = $this->getJson('/api/shop/products/featured', ['Accept' => 'application/json']);
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Featured One', $data[0]['name']);
    }

    public function test_admin_catalog_requires_auth(): void
    {
        $this->getJson('/api/admin/categories')->assertStatus(401);
        $this->getJson('/api/admin/services')->assertStatus(401);
        $this->getJson('/api/admin/products')->assertStatus(401);
    }

    // ---- Public Services (no auth) ----
    public function test_public_services_list_returns_success(): void
    {
        Service::factory()->count(2)->create(['is_active' => true]);
        $response = $this->getJson('/api/services', ['Accept' => 'application/json']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['data', 'pagination']]);
    }

    public function test_public_services_get_by_id_returns_success(): void
    {
        $service = Service::factory()->create(['name' => 'Public Service', 'is_active' => true]);
        $response = $this->getJson('/api/services/' . $service->id, ['Accept' => 'application/json']);
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Public Service');
    }
}
