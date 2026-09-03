<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use App\Enums\VendorStatus;
use App\Support\InstantOrderFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstantOrderFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private string $clientToken;

    private User $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('shop_shipping_amount', '0', 'text', 'shop');
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set(InstantOrderFee::SETTING_KEY, '15', 'text', 'shop');

        $this->client = User::factory()->create(['role' => 'client']);
        $this->clientToken = $this->client->createToken('test')->plainTextToken;

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function clientHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->clientToken,
        ];
    }

    private function adminHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->adminToken,
        ];
    }

    public function test_admin_can_update_instant_order_fee_setting(): void
    {
        $response = $this->putJson('/api/admin/settings/instant-order-fee', [
            'instant_order_fee_amount' => 25,
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('data.instant_order_fee_amount', 25)
            ->assertJsonPath('data.enabled', true);

        $this->assertSame(25.0, InstantOrderFee::amount());
    }

    public function test_shop_settings_api_can_update_instant_order_fee(): void
    {
        $response = $this->putJson('/api/admin/settings/shop', [
            'instant_order_fee_amount' => 20,
            'instant_order_fee_enabled' => true,
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('data.instant_order_fee_amount', 20)
            ->assertJsonPath('data.instant_order_fee_enabled', true);

        $this->assertSame(20.0, InstantOrderFee::amount());
    }

    public function test_cart_api_includes_instant_fee_in_order_summary(): void
    {
        Setting::set(InstantOrderFee::SETTING_KEY, '20', 'text', 'shop');
        Setting::set(InstantOrderFee::ENABLED_KEY, '1', 'text', 'shop');

        $category = Category::factory()->create(['shipping_cost' => 15, 'tax_percentage' => 5]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'simple product',
            'price' => 99,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Tax on 99 @ 5% = 4.95; shipping 15; fee 20 → total 138.95
        $this->getJson('/api/shop/cart', $this->clientHeaders())
            ->assertOk()
            ->assertJsonPath('data.order_summary.is_instant_order', true)
            ->assertJsonPath('data.order_summary.instant_order_fee', 20)
            ->assertJsonPath('data.order_summary.subtotal', 99)
            ->assertJsonPath('data.order_summary.shipping', 15)
            ->assertJsonPath('data.order_summary.tax', 4.95)
            ->assertJsonPath('data.order_summary.total', 138.95);
    }

    public function test_admin_can_get_instant_order_fee_setting(): void
    {
        $response = $this->getJson('/api/admin/settings/instant-order-fee', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('data.instant_order_fee_amount', 15)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.applies_to', 'instant_orders');
    }

    public function test_instant_product_order_summary_includes_fee_in_total(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 10, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'physical',
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->clientHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 15)
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.shipping', 10)
            ->assertJsonPath('data.total', 125);
    }

    public function test_booking_service_order_summary_does_not_include_instant_fee(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 10, 'tax_percentage' => 0]);
        $service = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'service',
            'price' => 200,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $service->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->clientHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_instant_order', false)
            ->assertJsonPath('data.instant_order_fee', 0)
            ->assertJsonPath('data.total', 210);
    }

    public function test_shop_settings_exposes_instant_order_fee_amount(): void
    {
        $response = $this->getJson('/api/shop/settings');

        $response->assertOk()
            ->assertJsonPath('data.instant_order_fee_amount', 15);
    }

    public function test_vendor_product_direct_checkout_includes_instant_fee(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Vendor Instant LLC',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::factory()->create(['shipping_cost' => 12, 'tax_percentage' => 0]);
        $vendorProduct = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'physical',
            'price' => 80,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $vendorProduct->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $vendorProduct->id,
            'quantity' => 2,
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->clientHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 15)
            ->assertJsonPath('data.subtotal', 160)
            ->assertJsonPath('data.shipping', 12)
            ->assertJsonPath('data.total', 187);
    }

    public function test_vendor_product_buy_now_summary_includes_instant_fee(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Buy Now Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::factory()->create(['shipping_cost' => 0, 'tax_percentage' => 0]);
        $vendorProduct = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'product',
            'price' => 50,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $vendorProduct->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $response = $this->getJson(
            '/api/shop/order-summary?product_id='.$vendorProduct->id.'&quantity=1',
            $this->clientHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 15)
            ->assertJsonPath('data.subtotal', 50)
            ->assertJsonPath('data.total', 65);
    }

    public function test_vendor_owned_booking_service_does_not_include_instant_fee(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Service Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::factory()->create(['shipping_cost' => 8, 'tax_percentage' => 0]);
        $vendorService = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'service',
            'price' => 120,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $vendorService->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $vendorService->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->clientHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_instant_order', false)
            ->assertJsonPath('data.instant_order_fee', 0)
            ->assertJsonPath('data.subtotal', 120)
            ->assertJsonPath('data.shipping', 8)
            ->assertJsonPath('data.total', 128);
    }

    public function test_mixed_vendor_product_and_booking_service_excludes_instant_fee(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 5, 'tax_percentage' => 0]);

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Mixed Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $vendorProduct = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'physical',
            'price' => 40,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $vendorProduct->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $service = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'service',
            'price' => 60,
            'status' => 'active',
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $vendorProduct->id,
            'quantity' => 1,
        ]);
        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $service->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->clientHeaders());

        $response->assertOk()
            ->assertJsonPath('data.is_instant_order', false)
            ->assertJsonPath('data.instant_order_fee', 0)
            ->assertJsonPath('data.subtotal', 100);
    }
}
