<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        }
    }

    public function test_unapproved_vendor_is_blocked_from_catalog_and_dashboard_apis(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::UnderReview);

        $this->withToken($token)->getJson('/api/vendor/profile')->assertForbidden();
        $this->withToken($token)->getJson('/api/vendor/application')->assertForbidden();
        $this->withToken($token)->getJson('/api/vendor/dashboard/summary')->assertForbidden();
        $this->withToken($token)->getJson('/api/vendor/dashboard/stats')->assertForbidden();
        $this->withToken($token)->getJson('/api/vendor/categories')->assertForbidden();
        $this->withToken($token)->postJson('/api/vendor/categories', [
            'name' => 'Blocked',
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ])->assertForbidden();
        $this->withToken($token)->getJson('/api/vendor/services')->assertForbidden();
        $this->withToken($token)->getJson('/api/vendor/orders')->assertForbidden();
    }

    public function test_vendor_category_crud_api(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);

        $create = $this->withToken($token)->post('/api/vendor/categories', [
            'name' => 'Vendor Produce',
            'description' => 'Fresh items',
            'shipping_cost' => 12.5,
            'tax_percentage' => 5,
            'is_active' => 1,
            'image' => UploadedFile::fake()->image('cat.jpg'),
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category.name', 'Vendor Produce')
            ->assertJsonPath('data.category.vendor_id', $vendor->id)
            ->assertJsonPath('data.category.is_platform', false);

        $categoryId = $create->json('data.category.id');

        $this->withToken($token)->getJson('/api/vendor/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'pagination']]);

        $this->withToken($token)->getJson("/api/vendor/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('data.category.id', $categoryId);

        $this->withToken($token)->post("/api/vendor/categories/{$categoryId}", [
            'name' => 'Vendor Produce Updated',
            'shipping_cost' => 15,
            'tax_percentage' => 7,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Vendor Produce Updated');

        $this->withToken($token)->deleteJson("/api/vendor/categories/{$categoryId}")
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    public function test_vendor_cannot_mutate_platform_category(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);

        $platform = Category::create([
            'name' => 'Platform Category',
            'slug' => 'platform-category',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $this->withToken($token)->getJson("/api/vendor/categories/{$platform->id}")
            ->assertOk()
            ->assertJsonPath('data.category.is_platform', true);

        $this->withToken($token)->post("/api/vendor/categories/{$platform->id}", [
            'name' => 'Hacked',
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->withToken($token)->deleteJson("/api/vendor/categories/{$platform->id}")
            ->assertForbidden();
    }

    public function test_vendor_service_crud_api(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'My Category',
            'slug' => 'my-category',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $create = $this->withToken($token)->post('/api/vendor/services', [
            'name' => 'Home Delivery',
            'description' => 'Same-day delivery',
            'category_id' => $category->id,
            'is_active' => 1,
            'image' => UploadedFile::fake()->image('service.jpg'),
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('data.service.name', 'Home Delivery')
            ->assertJsonPath('data.service.vendor_id', $vendor->id)
            ->assertJsonPath('data.service.category.id', $category->id);

        $serviceId = $create->json('data.service.id');

        $this->withToken($token)->getJson('/api/vendor/services')
            ->assertOk()
            ->assertJsonStructure(['data' => ['items', 'pagination']]);

        $this->withToken($token)->post("/api/vendor/services/{$serviceId}", [
            'name' => 'Express Delivery',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.service.name', 'Express Delivery');

        $this->withToken($token)->deleteJson("/api/vendor/services/{$serviceId}")
            ->assertOk();

        $this->assertDatabaseMissing('services', ['id' => $serviceId]);
    }

    public function test_dashboard_summary_returns_mobile_card_fields(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'Produce',
            'slug' => 'produce',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $product = \App\Models\Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Tomatoes',
            'price' => 25,
            'stock' => 10,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'guest_full_name' => 'Ahmed',
            'guest_email' => 'ahmed@test.com',
            'total_amount' => 90,
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);
        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Pending->value,
            'total_amount' => 90,
            'subtotal' => 90,
        ]);

        $this->withToken($token)->getJson('/api/vendor/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.currency', 'AED')
            ->assertJsonPath('data.revenue', 90)
            ->assertJsonPath('data.pending_orders', 1)
            ->assertJsonPath('data.products', 1)
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonStructure([
                'data' => ['currency', 'revenue', 'pending_orders', 'products', 'active', 'low_stock', 'total_orders', 'delivered_orders'],
            ]);
    }

    public function test_dashboard_stats_returns_full_overview(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);

        $this->withToken($token)->getJson('/api/vendor/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_products',
                    'revenue',
                    'pending_orders',
                    'recent_orders',
                    'inventory_alerts',
                    'analytics',
                ],
            ]);
    }

    public function test_vendor_product_create_links_services(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'Fruits',
            'slug' => 'fruits',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);
        $service = Service::create([
            'vendor_id' => $vendor->id,
            'name' => 'Delivery',
            'slug' => 'delivery',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $response = $this->withToken($token)->post('/api/vendor/products', [
            'name' => 'Organic Tomatoes',
            'category_id' => $category->id,
            'price' => 25,
            'stock' => 50,
            'service_ids' => [$service->id],
            'image' => UploadedFile::fake()->image('product.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.vendor_product.product.name', 'Organic Tomatoes');

        $productId = $response->json('data.vendor_product.product.id');
        $this->assertDatabaseHas('product_service', [
            'product_id' => $productId,
            'service_id' => $service->id,
        ]);
    }

    public function test_vendor_orders_list_includes_status_summary(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);

        $order = Order::create([
            'guest_full_name' => 'Customer',
            'guest_email' => 'c@test.com',
            'total_amount' => 50,
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);
        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Confirmed->value,
            'total_amount' => 50,
            'subtotal' => 50,
        ]);

        $this->withToken($token)->getJson('/api/vendor/orders')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.confirmed', 1)
            ->assertJsonStructure(['data' => ['summary', 'items', 'pagination']]);
    }

    public function test_vendor_login_returns_vendor_context(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email' => 'vendor-login@test.com',
            'password' => Hash::make('secret12'),
        ]);
        $user->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Login Test Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $this->postJson('/api/vendor/auth/login', [
            'email' => 'vendor-login@test.com',
            'password' => 'secret12',
            'roles' => 'vendor',
        ])
            ->assertOk()
            ->assertJsonPath('data.vendor.vendor_id', $vendor->id)
            ->assertJsonPath('data.vendor.status', VendorStatus::Approved->value)
            ->assertJsonPath('data.vendor.is_approved', true)
            ->assertJsonPath('data.vendor.business_name', 'Login Test Co');
    }

    /**
     * @return array{user: User, vendor: Vendor, token: string}
     */
    private function makeVendorUser(VendorStatus $status): array
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => $status->value,
            'approved_at' => $status === VendorStatus::Approved ? now() : null,
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Store',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        return [
            'user' => $user->fresh('vendor'),
            'vendor' => $vendor->fresh(),
            'token' => $token,
        ];
    }
}
