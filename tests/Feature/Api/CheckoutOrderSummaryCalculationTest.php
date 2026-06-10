<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Shop\CartController;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end checkout math: no coupon, apply, wallet on/off, remove — totals must stay consistent.
 */
class CheckoutOrderSummaryCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user): array
    {
        $token = $user->createToken('calc')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    }

    private function seedCheckoutCart(User $user): void
    {
        Setting::set('shop_shipping_amount', '120', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $cat = Category::factory()->create(['shipping_cost' => null, 'tax_percentage' => null]);
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 1438.50,
            'compare_at_price' => null,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $this->headers($user))->assertStatus(201);
    }

    private function assertSummaryMath(array $summary): void
    {
        CartController::assertOrderSummaryMath($summary);
    }

    public function test_full_checkout_flow_no_coupon_apply_wallet_remove(): void
    {
        $client = User::factory()->create(['role' => 'client', 'wallet_balance' => 137.02]);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'SAVE10',
            'title' => '10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $headers = $this->headers($client);
        $this->seedCheckoutCart($client);

        // 1) No coupon — full price
        $baseline = $this->getJson('/api/shop/order-summary', $headers)->assertOk();
        $baseSummary = $baseline->json('data');
        $this->assertSummaryMath($baseSummary);
        $this->assertSame(1438.50, (float) $baseSummary['subtotal']);
        $this->assertSame(120.0, (float) $baseSummary['shipping']);
        $this->assertSame(71.93, (float) $baseSummary['tax']);
        $this->assertSame(0.0, (float) $baseSummary['coupon_discount']);
        $this->assertNull($baseSummary['coupon_code'] ?? null);
        $this->assertSame(1630.43, (float) $baseSummary['total']);
        $fullTotal = (float) $baseSummary['total'];

        // 2) Apply coupon
        $apply = $this->postJson('/api/shop/coupons/apply', ['code' => 'SAVE10'], $headers)->assertOk();
        $withCoupon = $apply->json('data.order_summary');
        $this->assertSummaryMath($withCoupon);
        $this->assertSame('SAVE10', $withCoupon['coupon_code']);
        $this->assertSame(143.85, (float) $withCoupon['coupon_discount']);
        $this->assertSame(64.73, (float) $withCoupon['tax']);
        $this->assertSame(1479.38, (float) $withCoupon['total']);
        $discountedTotal = (float) $withCoupon['total'];

        // 3) Plain refresh — still full price (coupon not silently kept)
        $plain = $this->getJson('/api/shop/order-summary', $headers)->assertOk()->json('data');
        $this->assertSummaryMath($plain);
        $this->assertSame($fullTotal, (float) $plain['total']);
        $this->assertSame(0.0, (float) $plain['coupon_discount']);

        // 4) Explicit coupon on summary
        $explicit = $this->getJson('/api/shop/order-summary?coupon_code=SAVE10', $headers)->assertOk()->json('data');
        $this->assertSummaryMath($explicit);
        $this->assertSame($discountedTotal, (float) $explicit['total']);

        // 5) Wallet ON without coupon_code — coupon restored, amount_due reduced
        $walletOn = $this->getJson('/api/shop/order-summary?use_wallet=1', $headers)->assertOk()->json('data');
        $this->assertSummaryMath($walletOn);
        $this->assertSame($discountedTotal, (float) $walletOn['total']);
        $this->assertSame('SAVE10', $walletOn['coupon_code']);
        $this->assertSame(137.02, (float) $walletOn['wallet_amount_applied']);
        $this->assertSame(1342.36, (float) $walletOn['amount_due']);

        // 6) Remove coupon — back to full total
        $removed = $this->postJson('/api/shop/coupons/remove', [], $headers)->assertOk();
        $removedSummary = $removed->json('data.order_summary');
        $this->assertSummaryMath($removedSummary);
        $this->assertSame($fullTotal, (float) $removedSummary['total']);
        $this->assertSame(0.0, (float) $removedSummary['coupon_discount']);
        $this->assertArrayNotHasKey('coupon_code', $removedSummary);

        // 7) use_wallet=0 must NOT ghost-reapply coupon (was the production bug)
        $walletOff = $this->getJson('/api/shop/order-summary?use_wallet=0', $headers)->assertOk()->json('data');
        $this->assertSummaryMath($walletOff);
        $this->assertSame($fullTotal, (float) $walletOff['total']);
        $this->assertSame(0.0, (float) $walletOff['coupon_discount']);
        $this->assertNull($walletOff['coupon_code'] ?? null);
        $this->assertSame(71.93, (float) $walletOff['tax']);
    }
}
