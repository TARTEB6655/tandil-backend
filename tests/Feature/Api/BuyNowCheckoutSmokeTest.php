<?php

namespace Tests\Feature\Api;

use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\ProductAreaPricing;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test: full Buy Now API chain and response shape (matches mobile checkout screens).
 */
class BuyNowCheckoutSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Product $product;

    private ProductOption $optA;

    private ProductOption $optB;

    protected function setUp(): void
    {
        parent::setUp();

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Smoke Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);
        Service::factory()->create(['vendor_id' => $vendor->id]);

        $category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'service',
            'product_type' => 'variable',
            'price' => 100,
            'status' => 'active',
        ]);

        $g1 = ProductOptionGroup::create([
            'product_id' => $this->product->id,
            'name' => 'G1',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);
        $this->optA = ProductOption::create([
            'product_option_group_id' => $g1->id,
            'label' => 'A',
            'price_modifier' => 5,
            'sort_order' => 0,
        ]);
        $g2 = ProductOptionGroup::create([
            'product_id' => $this->product->id,
            'name' => 'G2',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $this->optB = ProductOption::create([
            'product_option_group_id' => $g2->id,
            'label' => 'B',
            'price_modifier' => 10,
            'sort_order' => 0,
        ]);

        ServiceAreaPricing::saveVendorMultiselect($vendor->id, true, true, 23, 70, ServiceAreaPricing::emptyIncludes(), true);
        ProductAreaPricing::save(
            $this->product,
            true,
            true,
            23,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true,
            false
        );
        ServiceTreePricing::saveGlobal(true, 50, 80);

        JobSchedulingSetting::current();
        JobTimeSlot::create(['start_time' => '09:00', 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        JobTimeSlot::create(['start_time' => '14:00', 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 2]);

        $this->client = User::factory()->create(['role' => 'client']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buyNowBody(string $mode = 'base'): array
    {
        $body = [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'selected_option_ids' => [$this->optA->id, $this->optB->id],
            'booking_date' => now()->addDays(3)->toDateString(),
            'booking_slot' => '02:00 PM',
        ];
        if ($mode === 'fixed') {
            $body['area_pricing_mode'] = 'fixed';
        }
        if ($mode === 'per_m2') {
            $body['area_pricing_mode'] = 'per_m2';
            $body['required_area'] = 7;
        }

        return $body;
    }

    public function test_smoke_buy_now_chain_base_mode(): void
    {
        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 200,
            'selected_options' => [$this->optA->id, $this->optB->id],
            'area_pricing_mode' => 'per_m2',
        ]);
        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 200,
            'selected_options' => [$this->optA->id, $this->optB->id],
            'area_pricing_mode' => 'per_m2',
        ]);

        $summary = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $this->buyNowBody('base'))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'order_summary' => [
                        'subtotal',
                        'total',
                        'tax',
                        'shipping',
                        'items',
                        'checkout_source',
                        'buy_now_product_id',
                    ],
                    'buy_now_checkout' => ['is_buy_now', 'product_id', 'params'],
                ],
            ])
            ->assertJsonPath('data.order_summary.checkout_source', 'buy_now')
            ->assertJsonPath('data.order_summary.subtotal', 115)
            ->json('data.order_summary');

        $this->assertSame(115.0, (float) ($summary['items'][0]['line_total'] ?? 0));

        // Mobile often skips is_buy_now on checkout/review — server restores cached Buy Now.
        $review = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/checkout/review')
            ->assertOk()
            ->assertJsonPath('data.order_summary.checkout_source', 'buy_now')
            ->assertJsonPath('data.order_summary.subtotal', 115)
            ->json('data.order_summary');

        $this->assertSame($summary['subtotal'], $review['subtotal']);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/order-summary')
            ->assertOk()
            ->assertJsonPath('data.checkout_source', 'buy_now')
            ->assertJsonPath('data.subtotal', 115);
    }

    public function test_smoke_product_id_with_stale_items_array_uses_top_level_body(): void
    {
        $body = $this->buyNowBody('fixed');
        $body['items'] = [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $this->product->id, 'quantity' => 1],
        ];

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $body)
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 38)
            ->assertJsonPath('data.order_summary.items.0.area_pricing_mode', 'fixed');
    }

    public function test_smoke_payment_intent_hydrates_area_mode_from_cache(): void
    {
        config(['services.stripe.secret' => 'sk_test_smoke_bn']);
        config(['services.stripe.key' => 'pk_test_smoke_bn']);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.stripe.com/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'pi_smoke_bn',
                'client_secret' => 'pi_smoke_bn_secret',
                'status' => 'requires_payment_method',
            ], 200),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $this->buyNowBody('fixed'))
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 38);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/checkout/stripe/payment-intent', [
                'is_buy_now' => true,
                'product_id' => $this->product->id,
                'quantity' => 1,
                'selected_option_ids' => [$this->optA->id, $this->optB->id],
                'booking_date' => now()->addDays(3)->toDateString(),
                'booking_slot' => '02:00 PM',
                'shipping' => [
                    'full_name' => 'Test',
                    'phone' => '+971500000000',
                    'street' => 'St',
                    'city' => 'Dubai',
                    'country' => 'AE',
                ],
            ])
            ->assertOk();
    }

    public function test_smoke_switch_per_m2_then_fixed_buy_now_without_stale_area(): void
    {
        config(['services.stripe.secret' => 'sk_test_smoke_switch']);
        config(['services.stripe.key' => 'pk_test_smoke_switch']);
        \Illuminate\Support\Facades\Http::fake([
            'https://api.stripe.com/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'pi_smoke_switch',
                'client_secret' => 'pi_smoke_switch_secret',
                'status' => 'requires_payment_method',
            ], 200),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $this->buyNowBody('per_m2'))
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 490);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/checkout/review')
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 490);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $this->buyNowBody('fixed'))
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 38)
            ->assertJsonPath('data.order_summary.items.0.area_pricing_mode', 'fixed')
            ->assertJsonPath('data.order_summary.items.0.required_area', null);

        // Mobile often posts payment-intent with shipping only after switching pricing on the product screen.
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/checkout/stripe/payment-intent', [
                'shipping' => [
                    'full_name' => 'Test',
                    'phone' => '+971500000000',
                    'street' => 'St',
                    'city' => 'Dubai',
                    'country' => 'AE',
                ],
            ])
            ->assertOk();
    }

    public function test_smoke_cart_checkout_uses_db_cart_when_checkout_mode_cart(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $this->buyNowBody('base'))
            ->assertOk();

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 115,
            'selected_options' => [$this->optA->id, $this->optB->id],
            'area_pricing_mode' => 'per_m2',
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/checkout/review?checkout_mode=cart')
            ->assertOk()
            ->assertJsonPath('data.order_summary.checkout_source', 'cart')
            ->assertJsonPath('data.order_summary.subtotal', 115);
    }
}
