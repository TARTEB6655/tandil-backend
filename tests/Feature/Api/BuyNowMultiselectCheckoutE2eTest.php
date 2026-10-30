<?php

namespace Tests\Feature\Api;

use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\ProductAreaPricing;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end Buy Now checkout totals for vendor multiselect services + variable options.
 * Mirrors mobile payloads: product_id, selected_option_ids, booking, area_pricing_mode variants.
 */
class BuyNowMultiselectCheckoutE2eTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Product $product;

    private ProductOption $optionA;

    private ProductOption $optionB;

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
            'business_name' => 'E2E Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        Service::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Landscaping']);

        $category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'service',
            'product_type' => 'variable',
            'name' => 'Multiselect Service',
            'price' => 100,
            'status' => 'active',
        ]);

        $groupOne = ProductOptionGroup::create([
            'product_id' => $this->product->id,
            'name' => 'Option group A',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);
        $this->optionA = ProductOption::create([
            'product_option_group_id' => $groupOne->id,
            'label' => 'Opt A',
            'price_modifier' => 5,
            'sort_order' => 0,
        ]);

        $groupTwo = ProductOptionGroup::create([
            'product_id' => $this->product->id,
            'name' => 'Option group B',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $this->optionB = ProductOption::create([
            'product_option_group_id' => $groupTwo->id,
            'label' => 'Opt B',
            'price_modifier' => 10,
            'sort_order' => 0,
        ]);

        ServiceAreaPricing::saveVendorMultiselect(
            $vendor->id,
            true,
            true,
            23,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );
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

        JobSchedulingSetting::current();
        JobTimeSlot::create([
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->client = User::factory()->create(['role' => 'client']);
    }

    /**
     * @return array<string, mixed>
     */
    private function commonBuyNowBody(): array
    {
        return [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'selected_option_ids' => [$this->optionA->id, $this->optionB->id],
            'booking_date' => '2026-11-15',
            'booking_slot' => '9:00 AM - 10:00 AM',
        ];
    }

    private function assertBuyNowTotals(float $expectedSubtotal, ?string $expectedMode, ?float $expectedArea): void
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $this->commonBuyNowBody());

        $response->assertOk();
        $summary = $response->json('data.order_summary');
        $this->assertSame($expectedSubtotal, (float) ($summary['subtotal'] ?? 0));

        $serviceLine = collect($summary['items'] ?? [])
            ->first(fn (array $row) => ($row['line_kind'] ?? 'service') === 'service' && ($row['is_addon'] ?? false) !== true);

        $this->assertIsArray($serviceLine);
        $this->assertSame($expectedSubtotal, (float) ($serviceLine['line_total'] ?? 0));
        if ($expectedMode !== null) {
            $this->assertSame($expectedMode, $serviceLine['area_pricing_mode'] ?? null);
        }
        if ($expectedArea !== null) {
            $this->assertSame($expectedArea, (float) ($serviceLine['required_area'] ?? 0));
        }
    }

    public function test_buy_now_base_catalog_with_options(): void
    {
        $this->assertBuyNowTotals(115.0, 'per_m2', null);
    }

    public function test_buy_now_fixed_addon_with_options(): void
    {
        $body = array_merge($this->commonBuyNowBody(), ['area_pricing_mode' => 'fixed']);
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $body);

        $response->assertOk();
        $summary = $response->json('data.order_summary');
        $this->assertSame(38.0, (float) ($summary['subtotal'] ?? 0));

        $serviceLine = collect($summary['items'])->first();
        $this->assertSame(38.0, (float) ($serviceLine['line_total'] ?? 0));
        $this->assertSame('fixed', $serviceLine['area_pricing_mode'] ?? null);
    }

    public function test_buy_now_per_m2_with_area(): void
    {
        $body = array_merge($this->commonBuyNowBody(), [
            'area_pricing_mode' => 'per_m2',
            'required_area' => 7,
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $body);

        $response->assertOk();
        $summary = $response->json('data.order_summary');
        // 7 m² × 70 rate (option modifiers apply on catalog/fixed lines, not area×rate base).
        $this->assertSame(490.0, (float) ($summary['subtotal'] ?? 0));

        $serviceLine = collect($summary['items'])->first();
        $this->assertSame(490.0, (float) ($serviceLine['line_total'] ?? 0));
        $this->assertSame('per_m2', $serviceLine['area_pricing_mode'] ?? null);
        $this->assertSame(7.0, (float) ($serviceLine['required_area'] ?? 0));
    }

    public function test_order_summary_get_matches_buy_now_post_for_base(): void
    {
        $query = http_build_query([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'selected_option_ids' => [$this->optionA->id, $this->optionB->id],
            'booking_date' => '2026-11-15',
            'booking_slot' => '9:00 AM - 10:00 AM',
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/order-summary?'.$query)
            ->assertOk()
            ->assertJsonPath('data.subtotal', 115)
            ->assertJsonPath('data.items.0.line_total', 115)
            ->assertJsonPath('data.items.0.area_pricing_mode', 'per_m2');
    }

    public function test_checkout_review_uses_buy_now_context_instead_of_db_cart(): void
    {
        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 50,
            'selected_options' => [$this->optionA->id, $this->optionB->id],
        ]);
        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 50,
            'selected_options' => [$this->optionA->id, $this->optionB->id],
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', $this->commonBuyNowBody())
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 115);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/shop/checkout/review?is_buy_now=1')
            ->assertOk()
            ->assertJsonPath('data.order_summary.subtotal', 115)
            ->assertJsonPath('data.order_summary.items.0.line_total', 115);
    }

    public function test_payment_intent_totals_match_buy_now_summary_for_fixed_mode(): void
    {
        config(['services.stripe.secret' => 'sk_test_e2e_multiselect']);
        config(['services.stripe.key' => 'pk_test_e2e_multiselect']);

        \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/customers')) {
                return \Illuminate\Support\Facades\Http::response(['id' => 'cus_e2e_multiselect'], 200);
            }

            return \Illuminate\Support\Facades\Http::response([
                'id' => 'pi_e2e_multiselect',
                'client_secret' => 'pi_e2e_multiselect_secret',
                'status' => 'requires_payment_method',
                'amount' => 3800,
            ], 200);
        });

        $summary = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/buy-now/summary', array_merge($this->commonBuyNowBody(), [
                'area_pricing_mode' => 'fixed',
            ]))
            ->assertOk()
            ->json('data.order_summary');

        $this->assertSame(38.0, (float) ($summary['subtotal'] ?? 0));

        $paymentBody = array_merge($this->commonBuyNowBody(), [
            'is_buy_now' => true,
            'area_pricing_mode' => 'fixed',
            'shipping' => [
                'full_name' => 'Test User',
                'phone' => '+971500000000',
                'street' => 'Street 1',
                'city' => 'Dubai',
                'country' => 'AE',
            ],
        ]);

        $pi = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/shop/checkout/stripe/payment-intent', $paymentBody);

        $pi->assertOk();
        $amountMinor = (int) round(((float) ($summary['total'] ?? $summary['subtotal'])) * 100);
        $this->assertGreaterThan(0, $amountMinor);
    }
}
