<?php

namespace Tests\Feature\Shop;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProductPrice;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientShopVendorStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        }
    }

    public function test_vendor_store_returns_vendor_details_and_all_products(): void
    {
        [$vendor, $product] = $this->seedVendorStoreListing('Green Valley Nursery', 48, 40);

        $this->getJson("/api/shop/vendors/{$vendor->id}")
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Green Valley Nursery')
            ->assertJsonPath('data.product_count', 1)
            ->assertJsonPath('data.products.0.product_id', $product->id)
            ->assertJsonPath('data.products.0.name', 'Fresh Seasonal Fruits Box')
            ->assertJsonPath('data.products.0.price', 48)
            ->assertJsonPath('data.products.0.stock_label', '40 in stock')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'business_name',
                    'logo_url',
                    'store_description',
                    'operating_hours',
                    'delivery_radius_km',
                    'product_count',
                    'products' => [
                        [
                            'product_id',
                            'vendor_product_id',
                            'name',
                            'price',
                            'image_url',
                            'stock_label',
                            'category',
                        ],
                    ],
                ],
            ]);
    }

    public function test_vendor_store_supports_search_filter(): void
    {
        [$vendor] = $this->seedVendorStoreListing('Search Vendor', 30, 10, 'Apple Juice');
        $this->seedVendorStoreListing('Search Vendor 2', 25, 8, 'Mango Shake', $vendor->id);

        $this->getJson("/api/shop/vendors/{$vendor->id}?search=Apple")
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Apple Juice')
            ->assertJsonPath('data.product_count', 1);
    }

    public function test_unapproved_vendor_store_returns_404(): void
    {
        [$vendor] = $this->seedVendorStoreListing('Pending Vendor', 20, 5, status: VendorStatus::UnderReview);

        $this->getJson("/api/shop/vendors/{$vendor->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: Vendor, 1: Product}
     */
    private function seedVendorStoreListing(
        string $businessName,
        float $price = 42,
        int $stock = 85,
        string $productName = 'Fresh Seasonal Fruits Box',
        ?int $existingVendorId = null,
        VendorStatus $status = VendorStatus::Approved,
    ): array {
        if ($existingVendorId) {
            $vendor = Vendor::findOrFail($existingVendorId);
        } else {
            $category = Category::create([
                'name' => 'Fruits',
                'slug' => 'fruits-'.uniqid(),
                'is_active' => true,
                'shipping_cost' => 0,
                'tax_percentage' => 0,
            ]);

            $vendorUser = User::factory()->create(['role' => 'vendor']);
            $vendorUser->assignRole('vendor');
            $vendor = Vendor::create([
                'user_id' => $vendorUser->id,
                'status' => $status->value,
                'approved_at' => $status === VendorStatus::Approved ? now() : null,
            ]);
            VendorProfile::create([
                'vendor_id' => $vendor->id,
                'business_name' => $businessName,
                'owner_name' => 'Owner',
                'email' => $vendorUser->email,
                'description' => 'Fresh produce supplier',
                'delivery_radius' => 15,
                'operating_hours' => '08:00 - 20:00',
            ]);
        }

        $categoryId = $existingVendorId
            ? Product::where('vendor_id', $vendor->id)->value('category_id')
            : Category::first()->id;

        $product = Product::create([
            'category_id' => $categoryId,
            'vendor_id' => $vendor->id,
            'name' => $productName,
            'price' => $price,
            'status' => 'active',
            'sort_order' => 1,
            'estimated_arrival' => '2 day delivery',
        ]);

        $vendorProduct = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        VendorProductPrice::create([
            'vendor_product_id' => $vendorProduct->id,
            'price' => $price,
            'effective_from' => now(),
        ]);

        VendorInventory::create([
            'vendor_product_id' => $vendorProduct->id,
            'quantity' => $stock,
        ]);

        return [$vendor, $product];
    }
}
