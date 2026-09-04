<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\InstantOrderFee;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rules:
 * - Instant Order Fee → shop/category (non-service) products only
 * - Service area pricing (Fixed / per m²) → service products only
 * - Service checkout must NOT get Instant Order Fee
 */
class ServicePricingVsInstantFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private string $token;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('shop_shipping_amount', '0', 'text', 'shop');
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set(InstantOrderFee::SETTING_KEY, '15', 'text', 'shop');

        ServiceAreaPricing::saveGlobal('per_m2', 70, [
            'materials' => true,
            'installation' => true,
            'labor' => true,
            'transportation' => false,
            'delivery' => false,
        ]);

        $this->client = User::factory()->create(['role' => 'client']);
        $this->token = $this->client->createToken('test')->plainTextToken;
        $this->category = Category::factory()->create([
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_instant_fee_applies_only_to_shop_product_checkout(): void
    {
        $shopProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'type' => 'product',
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 50,
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $shopProduct->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $this->getJson('/api/shop/order-summary', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 15)
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.total', 115);
    }

    public function test_instant_fee_not_added_on_service_product_checkout(): void
    {
        $serviceProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'type' => 'service',
            'price' => 70,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 999,
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $serviceProduct->id,
            'quantity' => 1,
            'unit_price' => 70,
            'required_area' => 100,
        ]);

        // 100 m² × 70 = 7000 — no instant fee
        $this->getJson('/api/shop/order-summary', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_instant_order', false)
            ->assertJsonPath('data.instant_order_fee', 0)
            ->assertJsonPath('data.subtotal', 7000)
            ->assertJsonPath('data.total', 7000);
    }

    public function test_service_area_pricing_only_on_service_products_not_shop_products(): void
    {
        $service = Product::factory()->create([
            'category_id' => $this->category->id,
            'type' => 'service',
            'price' => 10,
            'status' => 'active',
            'stock' => 999,
        ]);
        $shop = Product::factory()->create([
            'category_id' => $this->category->id,
            'type' => 'product',
            'price' => 50,
            'status' => 'active',
            'stock' => 20,
        ]);

        // Service detail: catalog price stays; per m² rate is separate
        $this->getJson('/api/shop/products/'.$service->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.requires_area', true)
            ->assertJsonPath('data.price', 10)
            ->assertJsonPath('data.price_per_m2', 70);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $service->id,
            'required_area' => 100,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.line_total', 7000)
            ->assertJsonPath('data.requires_area', true);

        // Shop product: no area pricing, Instant Fee path only
        $this->getJson('/api/shop/products/'.$shop->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.requires_area', false)
            ->assertJsonPath('data.price', 50);

        Cart::where('user_id', $this->client->id)->delete();

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $shop->id,
            'quantity' => 1,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.requires_area', false)
            ->assertJsonPath('data.line_total', 50)
            ->assertJsonPath('data.required_area', null);

        $summary = $this->getJson('/api/shop/order-summary', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 15)
            ->assertJsonPath('data.subtotal', 50);

        // Instant fee is on top of shop product subtotal (coupons may reduce total).
        $this->assertGreaterThanOrEqual(15, (float) $summary->json('data.total'));
        $this->assertSame(
            15.0,
            (float) $summary->json('data.instant_order_fee')
        );
    }

    public function test_mixed_cart_with_service_still_includes_instant_fee(): void
    {
        $shop = Product::factory()->create([
            'category_id' => $this->category->id,
            'type' => 'product',
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 10,
        ]);
        $service = Product::factory()->create([
            'category_id' => $this->category->id,
            'type' => 'service',
            'price' => 70,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 999,
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $shop->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);
        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $service->id,
            'quantity' => 1,
            'unit_price' => 70,
            'required_area' => 10,
        ]);

        // Service line 10×70=700 + shop 100 = 800; mixed cart still gets Instant Order Fee (15)
        $this->getJson('/api/shop/order-summary', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_instant_order', true)
            ->assertJsonPath('data.instant_order_fee', 15)
            ->assertJsonPath('data.subtotal', 800)
            ->assertJsonPath('data.total', 815);
    }
}
