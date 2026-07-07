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

class VendorApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    public function test_approved_vendor_full_catalog_flow(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);

        $category = Category::create([
            'name' => 'Fruits',
            'slug' => 'fruits',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);
        $service = Service::create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $this->withToken($token)->getJson('/api/vendor/categories')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $category->id);

        $this->withToken($token)->getJson('/api/vendor/services')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $service->id);

        $create = $this->withToken($token)->postJson('/api/vendor/products', [
            'name' => 'Integration Tomatoes',
            'price' => 30,
            'category_id' => $category->id,
            'service_id' => $service->id,
            'stock' => 25,
            'status' => 'active',
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.vendor_product.product.name', 'Integration Tomatoes')
            ->assertJsonPath('data.vendor_product.product.category_id', $category->id);

        $vendorProductId = $create->json('data.vendor_product.id');

        $this->withToken($token)->getJson('/api/vendor/products/'.$vendorProductId)
            ->assertOk()
            ->assertJsonPath('data.vendor_product.id', $vendorProductId);

        $this->withToken($token)->postJson('/api/vendor/products/'.$vendorProductId, [
            'price' => 35,
            'stock' => 20,
        ])->assertOk();

        $this->withToken($token)->getJson('/api/vendor/inventory/'.$vendorProductId)
            ->assertOk();

        $this->withToken($token)->postJson('/api/vendor/inventory/'.$vendorProductId, [
            'quantity' => 18,
            'low_stock_threshold' => 3,
        ])->assertOk();

        $this->withToken($token)->getJson('/api/vendor/dashboard/summary')->assertOk();
        $this->withToken($token)->getJson('/api/vendor/dashboard/stats')->assertOk();

        $this->withToken($token)->postJson('/api/vendor/categories', ['name' => 'Blocked'])->assertForbidden();
        $this->withToken($token)->postJson('/api/vendor/services', ['name' => 'Blocked'])->assertForbidden();

        $this->withToken($token)->deleteJson('/api/vendor/products/'.$vendorProductId)->assertOk();
    }

    public function test_product_create_uses_category_id_from_vendor_categories_list(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);

        Category::create([
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $list = $this->withToken($token)->getJson('/api/vendor/categories')->assertOk();
        $categoryId = $list->json('data.items.0.id');
        $this->assertNotNull($categoryId);

        $this->withToken($token)->postJson('/api/vendor/products', [
            'name' => 'Listed Category Product',
            'price' => 15,
            'category_id' => $categoryId,
        ])->assertCreated();
    }

    public function test_product_create_rejects_unknown_category_with_available_ids_hint(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);

        $category = Category::create([
            'name' => 'Platform Only',
            'slug' => 'platform-only',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $response = $this->withToken($token)->postJson('/api/vendor/products', [
            'name' => 'Bad Category Product',
            'price' => 10,
            'category_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $message = (string) $response->json('message');
        $this->assertStringContainsString((string) $category->id, $message);
        $this->assertStringContainsString('/api/vendor/categories', $message);
    }

    public function test_product_create_accepts_legacy_category_without_is_active_flag(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);

        $category = Category::create([
            'name' => 'Legacy Category',
            'slug' => 'legacy-category',
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $this->withToken($token)->getJson('/api/vendor/categories')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $category->id);

        $this->withToken($token)->postJson('/api/vendor/products', [
            'name' => 'Legacy Category Product',
            'price' => 12,
            'category_id' => $category->id,
        ])->assertCreated();
    }

    public function test_vendor_orders_and_profile_endpoints(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser(VendorStatus::Approved);

        $this->withToken($token)->getJson('/api/vendor/profile')->assertOk();
        $this->withToken($token)->getJson('/api/vendor/application')->assertOk();

        $order = Order::create([
            'guest_full_name' => 'Buyer',
            'guest_email' => 'buyer@test.com',
            'total_amount' => 40,
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);
        $mapping = VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Pending->value,
            'total_amount' => 40,
            'subtotal' => 40,
        ]);

        $this->withToken($token)->getJson('/api/vendor/orders')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id)->assertOk();

        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/status', [
            'status' => 'confirmed',
            'note' => 'Accepted',
        ])->assertOk();
    }

    public function test_product_create_with_multipart_form_data(): void
    {
        ['token' => $token] = $this->makeVendorUser(VendorStatus::Approved);

        $category = Category::create([
            'name' => 'Multipart Category',
            'slug' => 'multipart-category',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $this->withToken($token)->post('/api/vendor/products', [
            'name' => 'Multipart Product',
            'price' => 44,
            'category_id' => (string) $category->id,
            'stock' => 8,
            'main_image' => UploadedFile::fake()->image('product.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
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
            'business_name' => 'Integration Store',
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
