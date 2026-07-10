<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorProductStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_quantity_uses_vendor_inventory_when_present(): void
    {
        $vendorProduct = $this->makeVendorProduct(productStock: 100, inventoryQuantity: 12);

        $this->assertSame(12, $vendorProduct->stockQuantity());
    }

    public function test_stock_quantity_falls_back_to_product_stock_without_inventory_row(): void
    {
        $vendorProduct = $this->makeVendorProduct(productStock: 25, inventoryQuantity: null);

        $this->assertSame(25, $vendorProduct->stockQuantity());
        $this->assertFalse($vendorProduct->isLowStock());
        $this->assertFalse($vendorProduct->isOutOfStock());
    }

    public function test_stock_quantity_reports_out_of_stock_from_product_stock_fallback(): void
    {
        $vendorProduct = $this->makeVendorProduct(productStock: 0, inventoryQuantity: null);

        $this->assertSame(0, $vendorProduct->stockQuantity());
        $this->assertTrue($vendorProduct->isOutOfStock());
    }

    private function makeVendorProduct(int $productStock, ?int $inventoryQuantity): VendorProduct
    {
        $vendor = Vendor::create(['user_id' => User::factory()->create()->id, 'status' => 'approved']);
        $category = Category::create(['name' => 'Test', 'slug' => 'test-stock']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 10,
            'stock' => $productStock,
            'status' => 'active',
        ]);

        $vendorProduct = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        if ($inventoryQuantity !== null) {
            VendorInventory::create([
                'vendor_product_id' => $vendorProduct->id,
                'quantity' => $inventoryQuantity,
                'low_stock_threshold' => 5,
            ]);
        }

        return $vendorProduct->fresh(['inventory', 'product']);
    }
}
