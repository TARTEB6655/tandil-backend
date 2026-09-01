<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
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
        $response = $this->putJson('/api/admin/settings/shop', [
            'instant_order_fee_amount' => 25,
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('data.instant_order_fee_amount', 25);

        $this->assertSame(25.0, InstantOrderFee::amount());
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
}
