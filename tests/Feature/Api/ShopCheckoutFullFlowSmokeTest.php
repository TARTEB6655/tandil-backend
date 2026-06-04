<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Setting;
use App\Models\ShopMobileCheckout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end smoke: category shipping + variable options across summary, review, Stripe PI, guest checkout.
 */
class ShopCheckoutFullFlowSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function clientHeaders(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('flow')->plainTextToken,
        ];
    }

    private function shipping(): array
    {
        return [
            'full_name' => 'Flow User',
            'phone' => '+971501234567',
            'street' => 'Road 1',
            'city' => 'Dubai',
            'state' => 'DXB',
            'zip_code' => '00000',
            'country' => 'UAE',
        ];
    }

    /**
     * @return array{product: Product, premium_option_id: int}
     */
    private function createVariableProduct(Category $category, float $basePrice = 100, float $modifier = 20): array
    {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => $basePrice,
            'compare_at_price' => null,
            'status' => 'active',
            'product_type' => 'variable',
        ]);
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);
        ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'Standard',
            'price_modifier' => 0,
            'sort_order' => 0,
        ]);
        $premium = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'Large',
            'price_modifier' => $modifier,
            'sort_order' => 1,
        ]);

        return ['product' => $product, 'premium_option_id' => $premium->id];
    }

    public function test_cart_with_variable_options_and_category_shipping_totals(): void
    {
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set('shop_shipping_amount', '10', 'text', 'shop');

        $user = User::factory()->create(['role' => 'client']);
        $cat = Category::factory()->create(['shipping_cost' => 35, 'tax_percentage' => 0]);
        ['product' => $product, 'premium_option_id' => $optId] = $this->createVariableProduct($cat, 100, 25);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'selected_options' => [$optId],
            'unit_price' => 125,
        ]);

        $response = $this->getJson('/api/shop/cart', $this->clientHeaders($user));

        $response->assertOk();
        $summary = $response->json('data.order_summary');
        $this->assertSame(250.0, (float) $summary['subtotal']);
        $this->assertSame(35.0, (float) $summary['shipping']);
        $this->assertSame(285.0, (float) $summary['total']);
        $this->assertCount(1, $summary['category_shipping_breakdown']);
        $this->assertSame(125.0, (float) $response->json('data.items.0.current_price'));
    }

    public function test_checkout_review_includes_category_shipping_breakdown(): void
    {
        Setting::set('shop_tax_percent', '5', 'text', 'shop');
        Setting::set('shop_shipping_amount', '10', 'text', 'shop');

        $user = User::factory()->create(['role' => 'client']);
        $small = Category::factory()->create(['shipping_cost' => 12, 'tax_percentage' => 5]);
        $large = Category::factory()->create(['shipping_cost' => 40, 'tax_percentage' => 5]);
        $p1 = Product::factory()->create(['category_id' => $small->id, 'price' => 50, 'compare_at_price' => null, 'status' => 'active']);
        $p2 = Product::factory()->create(['category_id' => $large->id, 'price' => 80, 'compare_at_price' => null, 'status' => 'active']);

        Cart::create(['user_id' => $user->id, 'product_id' => $p1->id, 'quantity' => 1, 'unit_price' => 50]);
        Cart::create(['user_id' => $user->id, 'product_id' => $p2->id, 'quantity' => 1, 'unit_price' => 80]);

        $response = $this->getJson('/api/shop/checkout/review', $this->clientHeaders($user));

        $response->assertOk();
        $summary = $response->json('data.order_summary');
        $this->assertSame(130.0, (float) $summary['subtotal']);
        $this->assertSame(52.0, (float) $summary['shipping']);
        $this->assertCount(2, $summary['category_shipping_breakdown']);
        $expectedTotal = round(130 + 52 + (130 * 0.05), 2);
        $this->assertSame($expectedTotal, (float) $summary['total']);
    }

    public function test_stripe_payment_intent_matches_summary_with_category_shipping(): void
    {
        Config::set('services.stripe.secret', 'sk_test_flow');
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set('shop_shipping_amount', '10', 'text', 'shop');

        $user = User::factory()->create(['role' => 'client']);
        $cat = Category::factory()->create(['shipping_cost' => 30, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 200,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $summary = $this->getJson('/api/shop/order-summary', $this->clientHeaders($user));
        $summary->assertOk();
        $expectedTotal = (float) $summary->json('data.total');
        $expectedMinor = (int) round($expectedTotal * 100);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/customers')) {
                return Http::response(['id' => 'cus_flow'], 200);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), 'payment_intents')) {
                return Http::response([
                    'id' => 'pi_flow_cat',
                    'client_secret' => 'pi_flow_cat_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response([], 200);
        });

        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shipping(),
        ], $this->clientHeaders($user));

        $pi->assertOk();
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_summary.total'));
        $this->assertSame($expectedMinor, (int) $pi->json('data.amount_minor'));

        $row = ShopMobileCheckout::query()->where('stripe_payment_intent_id', 'pi_flow_cat')->first();
        $this->assertNotNull($row);
        $this->assertSame(30.0, (float) $row->shipping_amount);
        $this->assertSame(200.0, (float) $row->subtotal_amount);
    }

    public function test_guest_checkout_start_uses_category_shipping_on_order(): void
    {
        Setting::set('paypal_enabled', '1');
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set('shop_shipping_amount', '10', 'text', 'shop');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $cat = Category::factory()->create(['shipping_cost' => 22, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 80,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => 'guest@test.com',
            'full_name' => 'Guest',
            'phone_number' => '+971501234567',
            'street_address' => 'Road 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $order = Order::query()->latest('id')->first();
        $this->assertSame(80.0, (float) $order->subtotal_amount);
        $this->assertSame(22.0, (float) $order->shipping_amount);
        $this->assertSame(102.0, (float) $order->total_amount);
    }

    public function test_guest_checkout_with_option_ids_uses_option_price_in_subtotal(): void
    {
        Setting::set('paypal_enabled', '1');
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set('shop_shipping_amount', '0', 'text', 'shop');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $cat = Category::factory()->create(['shipping_cost' => 5, 'tax_percentage' => 0]);
        ['product' => $product, 'premium_option_id' => $optId] = $this->createVariableProduct($cat, 50, 15);

        $response = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => 'guest@test.com',
            'full_name' => 'Guest',
            'phone_number' => '+971501234567',
            'street_address' => 'Road 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'items' => [[
                'product_id' => $product->id,
                'qty' => 1,
                'option_ids' => [$optId],
            ]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $order = Order::query()->latest('id')->first();
        $this->assertSame(65.0, (float) $order->subtotal_amount);
        $this->assertSame(5.0, (float) $order->shipping_amount);
        $this->assertSame(70.0, (float) $order->total_amount);
        $this->assertSame(65.0, (float) $order->items->first()->price);
    }

    public function test_free_shipping_coupon_zeros_category_shipping(): void
    {
        Setting::set('shop_tax_percent', '0', 'text', 'shop');
        Setting::set('shop_shipping_amount', '10', 'text', 'shop');

        $user = User::factory()->create(['role' => 'client']);
        $cat = Category::factory()->create(['shipping_cost' => 40, 'tax_percentage' => 0]);
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        Coupon::create([
            'code' => 'FREESHIP',
            'title' => 'Free shipping',
            'discount_type' => 'free_shipping',
            'discount_value' => 0,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/shop/order-summary?coupon_code=FREESHIP', $this->clientHeaders($user));

        $response->assertOk();
        $this->assertSame(0.0, (float) $response->json('data.shipping'));
        $this->assertSame(100.0, (float) $response->json('data.total'));
    }

    public function test_shop_settings_public_api_lists_category_rates(): void
    {
        $cat = Category::factory()->create(['name' => 'Sheep', 'shipping_cost' => 18]);

        $response = $this->getJson('/api/shop/settings', ['Accept' => 'application/json']);

        $response->assertOk();
        $rates = $response->json('data.category_shipping_rates');
        $this->assertNotEmpty($rates);
        $match = collect($rates)->firstWhere('category_id', $cat->id);
        $this->assertSame(18.0, (float) ($match['shipping_cost'] ?? $match['shipping_amount']));
    }
}
