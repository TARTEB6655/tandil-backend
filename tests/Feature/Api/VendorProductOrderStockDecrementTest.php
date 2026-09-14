<?php

namespace Tests\Feature\Api;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use App\Support\OrderPaidSideEffects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorProductOrderStockDecrementTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_vendor_product_order_decrements_inventory_and_product_stock(): void
    {
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Stock Shop',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $client = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'price' => 50,
            'stock' => 999,
            'status' => 'active',
            'type' => 'product',
        ]);
        $vendorProduct = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        VendorInventory::create([
            'vendor_product_id' => $vendorProduct->id,
            'quantity' => 999,
            'low_stock_threshold' => 5,
        ]);

        $order = Order::create([
            'user_id' => $client->id,
            'total_amount' => 50,
            'subtotal_amount' => 50,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'order_status' => 'pending',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50,
        ]);

        OrderPaidSideEffects::run($order->fresh(['items.product.services']), 'Stripe (stock test)');

        $this->assertDatabaseHas('vendor_inventory', [
            'vendor_product_id' => $vendorProduct->id,
            'quantity' => 998,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 998,
        ]);
        $this->assertNotNull($order->fresh()->stock_decremented_at);

        // Re-run must not double-decrement.
        OrderPaidSideEffects::run($order->fresh(['items.product.services']), 'Stripe (stock test replay)');
        $this->assertDatabaseHas('vendor_inventory', [
            'vendor_product_id' => $vendorProduct->id,
            'quantity' => 998,
        ]);
        $this->assertSame(998, (int) $product->fresh()->stock);
    }
}
