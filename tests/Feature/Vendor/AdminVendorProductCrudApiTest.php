<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorProductCrudApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_admin_can_create_show_update_and_delete_vendor_product(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedApprovedVendor();
        $category = Category::create([
            'name' => 'Admin Cat',
            'slug' => 'admin-cat-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $create = $this->withToken($token)->postJson("/api/admin/vendors/{$vendor->id}/products", [
            'name' => 'Admin Created AC Clean',
            'price' => 99.5,
            'stock' => 12,
            'category_id' => $category->id,
            'description' => 'Created by admin for vendor',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.vendor_id', $vendor->id)
            ->assertJsonPath('data.vendor_product.product.name', 'Admin Created AC Clean')
            ->assertJsonPath('data.vendor_product.stock', 12)
            ->assertJsonPath('data.product.name', 'Admin Created AC Clean')
            ->assertJsonPath('data.product.is_enabled', true)
            ->assertJsonPath('data.product.price', 99.5)
            ->assertJsonStructure([
                'data' => [
                    'vendor_product' => [
                        'id',
                        'vendor_id',
                        'product_id',
                        'status',
                        'approval_status',
                        'product' => ['id', 'name', 'price'],
                    ],
                    'product' => [
                        'vendor_product_id',
                        'actions' => [
                            'show',
                            'update',
                            'toggle',
                            'delete',
                        ],
                    ],
                ],
            ]);

        $vendorProductId = (int) $create->json('data.vendor_product.id');
        $this->assertDatabaseHas('vendor_products', [
            'id' => $vendorProductId,
            'vendor_id' => $vendor->id,
            'approval_status' => 'approved',
            'status' => 'active',
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProductId}")
            ->assertOk()
            ->assertJsonPath('data.vendor_product.id', $vendorProductId)
            ->assertJsonPath('data.vendor_product.product.name', 'Admin Created AC Clean');

        $update = $this->withToken($token)->postJson(
            "/api/admin/vendors/{$vendor->id}/products/{$vendorProductId}",
            [
                'name' => 'Admin Updated AC Clean',
                'price' => 120,
                'stock' => 7,
                'category_id' => $category->id,
                'description' => 'Updated by admin',
            ]
        );

        $update->assertOk()
            ->assertJsonPath('data.vendor_product.product.name', 'Admin Updated AC Clean')
            ->assertJsonPath('data.product.price', 120)
            ->assertJsonPath('data.product.stock', 7)
            ->assertJsonPath('data.product.name', 'Admin Updated AC Clean');

        $catalogId = (int) $update->json('data.vendor_product.product_id');
        $this->assertDatabaseHas('products', [
            'id' => $catalogId,
            'name' => 'Admin Updated AC Clean',
            'price' => 120,
            'vendor_id' => $vendor->id,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProductId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.vendor_product_id', $vendorProductId);

        $this->assertNull(VendorProduct::find($vendorProductId));
        $this->assertDatabaseHas('products', [
            'id' => $catalogId,
            'status' => 'archived',
        ]);
    }

    public function test_admin_can_update_vendor_product_via_put_and_catalog_product_id(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedApprovedVendor();
        $category = Category::create([
            'name' => 'Put Cat',
            'slug' => 'put-cat-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Original Name',
            'price' => 40,
            'stock' => 3,
            'status' => 'active',
        ]);
        $vp = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->withToken($token)
            ->putJson("/api/admin/vendors/{$vendor->id}/products/{$product->id}", [
                'name' => 'Put Updated',
                'price' => 55,
                'stock' => 9,
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.vendor_product.id', $vp->id)
            ->assertJsonPath('data.product.name', 'Put Updated')
            ->assertJsonPath('data.product.price', 55)
            ->assertJsonPath('data.product.stock', 9);
    }

    public function test_admin_product_create_allows_missing_category_and_service_id(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedApprovedVendor();

        $response = $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/products", [
                'name' => 'Missing Category',
                'price' => 10,
            ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertSame('Missing Category', $response->json('data.vendor_product.product.name'));
    }

    public function test_non_admin_cannot_create_vendor_product(): void
    {
        ['vendor' => $vendor] = $this->seedApprovedVendor();
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $clientToken = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($clientToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/products", [
                'name' => 'Client Cannot',
                'price' => 10,
                'category_id' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_product_with_multipart_image(): void
    {
        Storage::fake('public');
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedApprovedVendor();
        $category = Category::create([
            'name' => 'Img Cat',
            'slug' => 'img-cat-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $response = $this->withToken($token)->post(
            "/api/admin/vendors/{$vendor->id}/products",
            [
                'name' => 'Multipart Product',
                'price' => 33,
                'stock' => 2,
                'category_id' => $category->id,
                'image' => UploadedFile::fake()->image('product.jpg'),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertCreated()
            ->assertJsonPath('data.product.name', 'Multipart Product');
        $this->assertNotEmpty($response->json('data.product.image_url'));
    }

    /**
     * @return array{adminToken: string, vendor: Vendor, vendorUser: User}
     */
    private function seedApprovedVendor(): array
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'password' => Hash::make('password'),
        ]);
        $vendorUser->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Admin CRUD Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
            'phone' => '+971500000099',
        ]);

        return [
            'adminToken' => $admin->createToken('t')->plainTextToken,
            'vendor' => $vendor,
            'vendorUser' => $vendorUser,
        ];
    }
}
