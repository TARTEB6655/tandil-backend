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

class ClientShopVendorCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        }
    }

    public function test_product_detail_shows_compare_when_multiple_vendors_in_category(): void
    {
        [$category, $productA] = $this->seedVendorListing('Green Valley Nursery', 42, 55, 85, '2 day delivery');
        $this->seedVendorListing('Desert Bloom Supplies', 48, null, 40, '1 day delivery', $category);

        $response = $this->getJson("/api/shop/products/{$productA->id}");

        $response->assertOk()
            ->assertJsonPath('data.compare_vendors.available', true)
            ->assertJsonPath('data.compare_vendors.vendor_count', 2)
            ->assertJsonPath('data.compare_vendors.label', 'Compare vendors & prices');
    }

    public function test_product_detail_hides_compare_for_single_vendor_category(): void
    {
        [, $product] = $this->seedVendorListing('Only Vendor', 30, null, 10, '2 day delivery');

        $this->getJson("/api/shop/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.compare_vendors.available', false)
            ->assertJsonPath('data.compare_vendors.vendor_count', 1);
    }

    public function test_compare_vendors_lists_category_listings_sorted_by_price(): void
    {
        [$category, $productA] = $this->seedVendorListing('Green Valley Nursery', 42, 55, 85, '2 day delivery');
        $this->seedVendorListing('Desert Bloom Supplies', 48, null, 40, '1 day delivery', $category);

        $response = $this->getJson("/api/shop/products/{$productA->id}/compare-vendors?sort_by=price");

        $response->assertOk()
            ->assertJsonPath('data.compare_available', true)
            ->assertJsonPath('data.vendor_count', 2)
            ->assertJsonPath('data.sort_by', 'price')
            ->assertJsonPath('data.vendors.0.vendor_name', 'Green Valley Nursery')
            ->assertJsonPath('data.vendors.0.price', 42)
            ->assertJsonPath('data.vendors.0.is_best_price', true)
            ->assertJsonPath('data.vendors.0.stock_label', '85 in stock')
            ->assertJsonPath('data.vendors.0.delivery_label', '2 day delivery')
            ->assertJsonPath('data.vendors.0.discount_percent', 24)
            ->assertJsonPath('data.vendors.1.vendor_name', 'Desert Bloom Supplies')
            ->assertJsonPath('data.vendors.1.price', 48);
    }

    public function test_compare_vendors_sorts_by_delivery_fastest(): void
    {
        [$category, $productA] = $this->seedVendorListing('Slow Vendor', 30, null, 10, '3 day delivery', null, 30);
        $this->seedVendorListing('Fast Vendor', 35, null, 12, '1 day delivery', $category, 8);

        $response = $this->getJson("/api/shop/products/{$productA->id}/compare-vendors?sort_by=delivery");

        $response->assertOk()
            ->assertJsonPath('data.vendors.0.vendor_name', 'Fast Vendor')
            ->assertJsonPath('data.vendors.0.delivery_days', 1)
            ->assertJsonPath('data.vendors.1.vendor_name', 'Slow Vendor')
            ->assertJsonPath('data.vendors.1.delivery_days', 3);
    }

    public function test_compare_excludes_unapproved_vendor_products(): void
    {
        [$category, $productA] = $this->seedVendorListing('Live Vendor', 40, null, 20, '2 day delivery');
        $this->seedVendorListing('Pending Vendor', 25, null, 15, '1 day delivery', $category, 10, VendorStatus::UnderReview);

        $this->getJson("/api/shop/products/{$productA->id}/compare-vendors")
            ->assertOk()
            ->assertJsonPath('data.compare_available', false)
            ->assertJsonPath('data.vendor_count', 1)
            ->assertJsonCount(1, 'data.vendors');
    }

    /**
     * @return array{0: Category, 1: Product}
     */
    private function seedVendorListing(
        string $businessName,
        float $price,
        ?float $compareAt,
        int $stock,
        string $estimatedArrival,
        ?Category $category = null,
        ?float $deliveryRadius = null,
        VendorStatus $status = VendorStatus::Approved,
    ): array {
        $category ??= Category::create([
            'name' => 'Plants',
            'slug' => 'plants-'.uniqid(),
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
            'delivery_radius' => $deliveryRadius,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'name' => 'test product 2',
            'price' => $price,
            'compare_at_price' => $compareAt,
            'status' => 'active',
            'sort_order' => 1,
            'estimated_arrival' => $estimatedArrival,
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
            'compare_at_price' => $compareAt,
            'effective_from' => now(),
        ]);

        VendorInventory::create([
            'vendor_product_id' => $vendorProduct->id,
            'quantity' => $stock,
        ]);

        return [$category, $product];
    }
}
