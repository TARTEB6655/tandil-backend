<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorProductGalleryImageTest extends TestCase
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

    public function test_vendor_can_remove_single_gallery_image_on_update(): void
    {
        ['token' => $token, 'vendorProductId' => $vendorProductId, 'galleryIds' => $galleryIds] = $this->createVendorProductWithGallery();

        $removeId = $galleryIds[0];
        $keepId = $galleryIds[1];

        $response = $this->withToken($token)->post("/api/vendor/products/{$vendorProductId}", [
            'removed_image_ids' => [(string) $removeId],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.vendor_product.product.gallery_images.0.id', $keepId)
            ->assertJsonMissing(['id' => $removeId]);

        $this->assertDatabaseMissing('product_images', ['id' => $removeId]);
        $this->assertDatabaseHas('product_images', ['id' => $keepId]);
    }

    public function test_vendor_can_sync_gallery_using_keep_image_ids(): void
    {
        ['token' => $token, 'vendorProductId' => $vendorProductId, 'galleryIds' => $galleryIds, 'primaryId' => $primaryId] = $this->createVendorProductWithGallery();

        $removeId = $galleryIds[0];
        $keepId = $galleryIds[1];

        $this->withToken($token)->post("/api/vendor/products/{$vendorProductId}", [
            'keep_image_ids' => [(string) $primaryId, (string) $keepId],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(1, 'data.vendor_product.product.gallery_images');

        $this->assertDatabaseMissing('product_images', ['id' => $removeId]);
        $this->assertDatabaseHas('product_images', ['id' => $keepId, 'is_primary' => false]);
        $this->assertDatabaseHas('product_images', ['id' => $primaryId, 'is_primary' => true]);
    }

    public function test_removed_gallery_image_stays_deleted_after_reload(): void
    {
        ['token' => $token, 'vendorProductId' => $vendorProductId, 'galleryIds' => $galleryIds] = $this->createVendorProductWithGallery();

        $removeId = $galleryIds[0];

        $this->withToken($token)->post("/api/vendor/products/{$vendorProductId}", [
            'remove_image_ids' => json_encode([$removeId]),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->withToken($token)->getJson("/api/vendor/products/{$vendorProductId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.vendor_product.product.gallery_images')
            ->assertJsonMissing(['id' => $removeId]);
    }

    /**
     * @return array{token: string, vendorProductId: int, primaryId: int, galleryIds: list<int>}
     */
    private function createVendorProductWithGallery(): array
    {
        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Gallery Store',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $category = Category::create([
            'name' => 'Gallery Category',
            'slug' => 'gallery-category',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        $create = $this->withToken($token)->post('/api/vendor/products', [
            'name' => 'Gallery Product',
            'price' => 50,
            'category_id' => (string) $category->id,
            'main_image' => UploadedFile::fake()->image('main.jpg'),
            'images' => [
                UploadedFile::fake()->image('gallery-1.jpg'),
                UploadedFile::fake()->image('gallery-2.jpg'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $vendorProductId = (int) $create->json('data.vendor_product.id');
        $productId = (int) $create->json('data.vendor_product.product_id');

        $primaryId = (int) ProductImage::where('product_id', $productId)->where('is_primary', true)->value('id');
        $galleryIds = ProductImage::where('product_id', $productId)
            ->where('is_primary', false)
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertCount(2, $galleryIds);

        return [
            'token' => $token,
            'vendorProductId' => $vendorProductId,
            'primaryId' => $primaryId,
            'galleryIds' => $galleryIds,
        ];
    }
}
