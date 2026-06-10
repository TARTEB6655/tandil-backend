<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShopAppliedCheckoutCoupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Smoke: order-summary and payment-intent must always return the same totals (with/without coupon).
 */
class CheckoutStripeTotalsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user): array
    {
        $token = $user->createToken('smoke')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    private function shipping(): array
    {
        return [
            'full_name' => 'Smoke User',
            'phone' => '+971501234567',
            'street' => 'Sheikh Zayed Road',
            'city' => 'Dubai',
            'state' => 'DXB',
            'zip_code' => '00000',
            'country' => 'UAE',
        ];
    }

    private function seedCart(User $user, float $price = 820.0): Product
    {
        $cat = Category::factory()->create(['shipping_cost' => null]);
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => $price,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        return $product;
    }

    private function fakeStripe(int $amountMinor, string $piId = 'pi_smoke_test'): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($amountMinor, $piId) {
            $url = $request->url();
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_smoke'], 200);
            }
            if (str_contains($url, 'payment_intents/'.$piId) && $request->method() === 'GET') {
                return Http::response([
                    'id' => $piId,
                    'status' => 'requires_payment_method',
                    'amount' => $amountMinor,
                    'client_secret' => $piId.'_secret',
                ], 200);
            }
            if (str_contains($url, 'payment_intents/'.$piId) && $request->method() === 'POST') {
                return Http::response([
                    'id' => $piId,
                    'status' => 'requires_payment_method',
                    'amount' => (int) ($request->data()['amount'] ?? $amountMinor),
                    'client_secret' => $piId.'_secret',
                ], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => $piId,
                    'client_secret' => $piId.'_secret',
                    'status' => 'requires_payment_method',
                    'amount' => (int) ($request->data()['amount'] ?? 0),
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected '.$url]], 500);
        });
    }

    public function test_smoke_without_coupon_order_summary_matches_payment_intent(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $this->seedCart($user, 820);

        $summary = $this->getJson('/api/shop/order-summary', $this->headers($user));
        $summary->assertOk();
        $expectedTotal = (float) $summary->json('data.total');
        $this->assertSame(871.0, $expectedTotal);

        $this->fakeStripe(87100);
        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shipping(),
        ], $this->headers($user));

        $pi->assertOk();
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_total'));
        $this->assertSame($expectedTotal, (float) $pi->json('data.amount_due'));
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_summary.total'));
        $this->assertNull($pi->json('data.order_summary.coupon_code'));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'payment_intents')
                && ! str_contains($request->url(), 'pi_smoke_test/')
                && (int) ($request->data()['amount'] ?? 0) === 87100;
        });
    }

    public function test_smoke_with_coupon_apply_requires_coupon_code_on_summary_and_payment_intent(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $this->seedCart($user, 820);

        Coupon::create([
            'code' => 'FLAT20',
            'title' => '20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $headers = $this->headers($user);

        $this->postJson('/api/shop/coupons/apply', ['code' => 'FLAT20'], $headers)->assertOk();

        $without = $this->getJson('/api/shop/order-summary', $headers);
        $without->assertOk();
        $this->assertSame(871.0, (float) $without->json('data.total'));
        $this->assertNull($without->json('data.coupon_code'));

        $summary = $this->getJson('/api/shop/order-summary?coupon_code=FLAT20', $headers);
        $summary->assertOk();
        $expectedTotal = (float) $summary->json('data.total');
        $this->assertSame('FLAT20', $summary->json('data.coupon_code'));
        $this->assertSame(850.0, $expectedTotal);

        $this->fakeStripe(85000);
        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shipping(),
            'coupon_code' => 'FLAT20',
        ], $headers);

        $pi->assertOk();
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_total'));
        $this->assertSame('FLAT20', $pi->json('data.order_summary.coupon_code'));
    }

    public function test_payment_intent_accepts_code_alias_same_as_coupon_code_on_order_summary(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $this->seedCart($user, 820);
        $headers = $this->headers($user);

        Coupon::create([
            'code' => 'FLAT20',
            'title' => '20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $summary = $this->getJson('/api/shop/order-summary?coupon_code=FLAT20', $headers);
        $summary->assertOk();
        $expectedTotal = (float) $summary->json('data.total');
        $this->assertSame(850.0, $expectedTotal);

        $this->fakeStripe(85000);
        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shipping(),
            'code' => 'FLAT20',
        ], $headers);

        $pi->assertOk();
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_total'));
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_summary.total'));
        $this->assertSame('FLAT20', $pi->json('data.order_summary.coupon_code'));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'payment_intents')
                && ! str_contains($request->url(), 'pi_smoke_test/')
                && (int) ($request->data()['amount'] ?? 0) === 85000;
        });
    }

    public function test_order_summary_without_coupon_param_is_full_price_not_remembered_apply(): void
    {
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $this->seedCart($user, 820);

        Coupon::create([
            'code' => 'FLAT20',
            'title' => '20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $headers = $this->headers($user);
        $this->postJson('/api/shop/coupons/apply', ['code' => 'FLAT20'], $headers)->assertOk();

        $this->getJson('/api/shop/order-summary', $headers)
            ->assertOk()
            ->assertJsonPath('data.total', 871)
            ->assertJsonPath('data.coupon_code', null)
            ->assertJsonPath('data.tax', 41);
    }

    public function test_smoke_pi_created_before_apply_is_reconciled_to_discounted_total(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $this->seedCart($user, 820);
        $headers = $this->headers($user);

        Coupon::create([
            'code' => 'FLAT20',
            'title' => '20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $this->fakeStripe(87100, 'pi_before_apply');
        $before = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shipping(),
        ], $headers);
        $before->assertOk();
        $this->assertSame(871.0, (float) $before->json('data.order_total'));

        $stripeAmount = 87100;
        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$stripeAmount) {
            $url = $request->url();
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_smoke2'], 200);
            }
            if (str_contains($url, 'payment_intents/pi_before_apply') && $request->method() === 'GET') {
                return Http::response([
                    'id' => 'pi_before_apply',
                    'status' => 'requires_payment_method',
                    'amount' => $stripeAmount,
                    'client_secret' => 'pi_before_apply_secret',
                ], 200);
            }
            if (str_contains($url, 'payment_intents/pi_before_apply') && $request->method() === 'POST') {
                $stripeAmount = (int) ($request->data()['amount'] ?? $stripeAmount);

                return Http::response([
                    'id' => 'pi_before_apply',
                    'status' => 'requires_payment_method',
                    'amount' => $stripeAmount,
                    'client_secret' => 'pi_before_apply_secret',
                ], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_new',
                    'client_secret' => 'pi_new_secret',
                    'status' => 'requires_payment_method',
                    'amount' => (int) ($request->data()['amount'] ?? 0),
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $this->postJson('/api/shop/coupons/apply', ['code' => 'FLAT20'], $headers)->assertOk();

        $summary = $this->getJson('/api/shop/order-summary?coupon_code=FLAT20', $headers);
        $discounted = (float) $summary->json('data.total');
        $this->assertSame(850.0, $discounted);

        $after = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shipping(),
            'coupon_code' => 'FLAT20',
        ], $headers);

        $after->assertOk();
        $this->assertSame($discounted, (float) $after->json('data.order_total'));
        $this->assertSame($discounted, (float) $after->json('data.amount_due'));
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($discounted): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'payment_intents/pi_before_apply')
                && (int) ($request->data()['amount'] ?? 0) === (int) round($discounted * 100);
        });
    }

    public function test_clear_coupon_removes_db_session_and_full_total(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $this->seedCart($user, 820);
        $headers = $this->headers($user);

        Coupon::create([
            'code' => 'FLAT20',
            'title' => '20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $this->postJson('/api/shop/coupons/apply', ['code' => 'FLAT20'], $headers)->assertOk();
        $this->assertDatabaseHas('shop_applied_checkout_coupons', ['user_id' => $user->id]);

        $this->getJson('/api/shop/order-summary?clear_coupon=1', $headers)->assertOk();
        $this->assertDatabaseMissing('shop_applied_checkout_coupons', ['user_id' => $user->id]);

        $summary = $this->getJson('/api/shop/order-summary', $headers);
        $this->assertSame(871.0, (float) $summary->json('data.total'));
    }
}
