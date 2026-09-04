<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\InstantOrderFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * باقة الاندسكيب الماسية lives under Services (product_service link).
 * Instant Order Fee must NOT apply — fee is for shop/simple products only.
 */
class DiamondPackageInstantFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('shop_shipping_amount', '0', 'text', 'shop');
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set(InstantOrderFee::SETTING_KEY, '20', 'text', 'shop');
        Setting::set(InstantOrderFee::ENABLED_KEY, '1', 'text', 'shop');

        $this->client = User::factory()->create(['role' => 'client']);
        $this->clientToken = $this->client->createToken('test')->plainTextToken;
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->clientToken,
        ];
    }

    public function test_service_linked_design_package_skips_instant_fee(): void
    {
        $category = Category::factory()->create([
            'name' => 'التصميم',
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => null,
            'type' => 'product',
            'product_type' => 'variable',
            'name' => 'باقة الاندسكيب الماسية',
            'price' => 1200,
            'compare_at_price' => null,
            'status' => 'active',
            'stock' => 999,
            'job_duration' => '1-4 ساعات',
        ]);

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'المدينة',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);
        $cityOption = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'العين',
            'price_modifier' => 300,
            'sort_order' => 0,
        ]);

        // Listed under Services → Instant Fee must not apply.
        $service = Service::create([
            'name' => 'Design Service',
            'slug' => 'design-service-'.uniqid(),
            'is_active' => true,
            'category_id' => $category->id,
        ]);
        $product->services()->attach($service->id);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_options' => [$cityOption->id],
            'unit_price' => 1500,
        ]);

        $cart = $this->getJson('/api/shop/cart', $this->headers());

        $cart->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 1500)
            ->assertJsonPath('data.order_summary.instant_order_fee', 0)
            ->assertJsonPath('data.order_summary.is_instant_order', false)
            ->assertJsonPath('data.order_summary.total', 1500)
            ->assertJsonPath('data.items.0.is_instant_eligible', false)
            ->assertJsonPath('data.items.0.is_service', true);

        $this->assertSame('service_only_cart', $cart->json('data.order_summary.instant_order_fee_skipped_reason'));
    }

    public function test_null_type_with_service_pivot_skips_instant_fee(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 0, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'type' => null,
            'product_type' => 'variable',
            'name' => 'Package with empty type',
            'price' => 1500,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        $service = Service::create([
            'name' => 'Linked Service',
            'slug' => 'linked-'.uniqid(),
            'is_active' => true,
            'category_id' => $category->id,
        ]);
        $product->services()->attach($service->id);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->getJson('/api/shop/cart', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.order_summary.instant_order_fee', 0)
            ->assertJsonPath('data.order_summary.is_instant_order', false)
            ->assertJsonPath('data.order_summary.total', 1500)
            ->assertJsonPath('data.items.0.is_instant_eligible', false);
    }

    public function test_shop_product_without_service_link_still_gets_instant_fee(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 0, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'product_type' => 'simple',
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

        $this->getJson('/api/shop/cart', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.order_summary.instant_order_fee', 20)
            ->assertJsonPath('data.order_summary.is_instant_order', true)
            ->assertJsonPath('data.order_summary.total', 119)
            ->assertJsonPath('data.items.0.is_instant_eligible', true);
    }

    public function test_explicit_service_type_still_skips_instant_fee(): void
    {
        $category = Category::factory()->create(['shipping_cost' => 0, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'service',
            'price' => 1500,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->getJson('/api/shop/cart', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.order_summary.instant_order_fee', 0)
            ->assertJsonPath('data.order_summary.instant_order_fee_skipped_reason', 'service_only_cart')
            ->assertJsonPath('data.order_summary.total', 1500);
    }
}
