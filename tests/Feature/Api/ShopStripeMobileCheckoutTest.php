<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Shop\CartController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShopMobileCheckout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopStripeMobileCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('t')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    private function shippingPayload(string $country = 'UAE'): array
    {
        return [
            'full_name' => 'Test User',
            'phone' => '+971501234567',
            'street' => 'Sheikh Zayed Road',
            'city' => 'Dubai',
            'state' => 'DXB',
            'zip_code' => '00000',
            'country' => $country,
        ];
    }

    public function test_payment_intent_proceeds_when_admin_keys_override_env(): void
    {
        Config::set('services.stripe.secret', 'sk_test_env_only');
        Config::set('services.stripe.key', 'pk_test_env_only');
        Setting::set('stripe_test_secret_key', 'sk_test_admin');
        Setting::set('stripe_test_public_key', 'pk_test_admin');
        Setting::set('stripe_mode', 'test', 'text', 'payment');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create(['shipping_cost' => null]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 820,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/customers')) {
                return Http::response(['id' => 'cus_admin_keys'], 200);
            }
            if (str_contains($request->url(), 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_admin_keys',
                    'client_secret' => 'pi_admin_keys_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.stripe_diagnostics.configuration_notes.0', 'Admin dashboard Stripe keys override .env. Use the Test/Live mode toggle here; .env keys are only a fallback when admin keys are empty.');
    }

    public function test_payment_intent_returns_422_when_stripe_keys_mode_mismatch(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Config::set('services.stripe.key', 'pk_live_dummy');
        $user = User::factory()->create(['role' => 'client']);

        $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_stripe_config_endpoint_returns_publishable_key_and_mode(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Config::set('services.stripe.key', 'pk_test_dummy');
        $user = User::factory()->create(['role' => 'client']);

        $this->getJson('/api/shop/checkout/stripe/config', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.stripe_mode', 'test')
            ->assertJsonPath('data.publishable_key', 'pk_test_dummy')
            ->assertJsonPath('data.stripe_keys_version', 0)
            ->assertJsonPath('data.stripe_diagnostics.secret_key_prefix', 'sk_test_dumm…');
    }

    public function test_payment_methods_includes_stripe_publishable_key_for_mobile(): void
    {
        Config::set('services.stripe.secret', 'sk_test_mobile');
        Config::set('services.stripe.key', 'pk_test_mobile');
        $user = User::factory()->create(['role' => 'client']);

        $this->getJson('/api/shop/checkout/payment-methods', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.stripe.publishable_key', 'pk_test_mobile')
            ->assertJsonPath('data.stripe.stripe_mode', 'test')
            ->assertJsonPath('data.methods.0.publishable_key', 'pk_test_mobile');
    }

    public function test_payment_intent_returns_422_when_stripe_not_configured(): void
    {
        Config::set('services.stripe.secret', '');
        $user = User::factory()->create(['role' => 'client']);

        $response = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $this->authHeaders($user));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_payment_intent_then_confirm_creates_paid_order(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 65,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $amountMinor = 7825;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($amountMinor) {
            $url = $request->url();
            if (str_contains($url, 'payment_intents/pi_test_abc123') && $request->method() === 'GET') {
                return Http::response([
                    'id' => 'pi_test_abc123',
                    'status' => 'succeeded',
                    'amount' => $amountMinor,
                ], 200);
            }
            if ($request->method() === 'POST' && preg_match('#/v1/customers/cus_[a-zA-Z0-9_]+$#', $url)) {
                return Http::response(['id' => 'cus_test_pi'], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_pi'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_test_abc123',
                    'client_secret' => 'pi_test_abc123_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected URL '.$url]], 500);
        });

        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $this->authHeaders($user));

        $pi->assertStatus(200);
        $pi->assertJsonPath('success', true);
        $pi->assertJsonPath('data.client_secret', 'pi_test_abc123_secret');

        $confirm = $this->postJson('/api/shop/checkout/confirm', [
            'payment_intent_id' => 'pi_test_abc123',
        ], $this->authHeaders($user));

        $confirm->assertStatus(201);
        $confirm->assertJsonPath('success', true);
        $confirm->assertJsonPath('data.payment_status', 'paid');
        $this->assertSame(78.25, (float) $confirm->json('data.total_amount'));

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_confirm_idempotent_when_order_already_exists(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '0');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

        $amountMinor = 10500;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($amountMinor) {
            $url = $request->url();
            if (str_contains($url, 'payment_intents/pi_test_dup') && $request->method() === 'GET') {
                return Http::response([
                    'id' => 'pi_test_dup',
                    'status' => 'succeeded',
                    'amount' => $amountMinor,
                ], 200);
            }
            if ($request->method() === 'POST' && preg_match('#/v1/customers/cus_[a-zA-Z0-9_]+$#', $url)) {
                return Http::response(['id' => 'cus_test_dup'], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_dup'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_test_dup',
                    'client_secret' => 'pi_test_dup_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $this->authHeaders($user))->assertStatus(200);

        $this->postJson('/api/shop/checkout/confirm', [
            'payment_intent_id' => 'pi_test_dup',
        ], $this->authHeaders($user))->assertStatus(201);

        $second = $this->postJson('/api/shop/checkout/confirm', [
            'payment_intent_id' => 'pi_test_dup',
        ], $this->authHeaders($user));

        $second->assertStatus(200);
        $second->assertJsonPath('message', 'Order already confirmed.');
    }

    public function test_payment_intent_normalizes_arabic_country_to_iso_code_for_stripe(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 65,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if ($request->method() === 'POST' && preg_match('#/v1/customers/cus_[a-zA-Z0-9_]+$#', $url)) {
                return Http::response(['id' => 'cus_test_ar'], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_ar'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_test_ar',
                    'client_secret' => 'pi_test_ar_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected URL '.$url]], 500);
        });

        $response = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload('متحدہ عرب امارات'),
        ], $this->authHeaders($user));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.shipping_country_iso', 'AE');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/v1/payment_intents')) {
                return false;
            }

            $form = $request->data();
            return ($form['shipping[address][country]'] ?? null) === 'AE'
                && ($form['metadata[ship_country_iso]'] ?? null) === 'AE';
        });
    }

    public function test_payment_intent_normalizes_urdu_country_to_iso_code_for_stripe(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 65,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if ($request->method() === 'POST' && preg_match('#/v1/customers/cus_[a-zA-Z0-9_]+$#', $url)) {
                return Http::response(['id' => 'cus_test_ur'], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_ur'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_test_ur',
                    'client_secret' => 'pi_test_ur_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected URL '.$url]], 500);
        });

        $response = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload('متحدہ عرب امارات (UAE)'),
        ], $this->authHeaders($user));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.shipping_country_iso', 'AE');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/v1/payment_intents')) {
                return false;
            }

            $form = $request->data();
            return ($form['shipping[address][country]'] ?? null) === 'AE'
                && ($form['metadata[ship_country_iso]'] ?? null) === 'AE';
        });
    }

    public function test_payment_intent_uses_product_quantity_when_cart_has_different_qty(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 25,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_qty'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_test_qty',
                    'client_secret' => 'pi_test_qty_secret',
                    'status' => 'requires_payment_method',
                    'amount' => (int) ($request->data()['amount'] ?? 0),
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $response = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'product_id' => $product->id,
            'quantity' => 2,
            'shipping' => $this->shippingPayload(),
        ], $this->authHeaders($user));

        $response->assertStatus(200);
        $response->assertJsonPath('data.preview_subtotal', 50);
        $response->assertJsonPath('data.preview_quantity', 2);
        $response->assertJsonPath('data.checkout_source', 'product_buy_now');
        // 50 - 0 coupon + 10 ship + 5% tax on 50 = 62.5
        $response->assertJsonPath('data.order_total', 62.5);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'payment_intents')
                && (int) ($request->data()['amount'] ?? 0) === 6250;
        });
    }

    public function test_payment_intent_does_not_inherit_coupon_from_old_pending_checkout(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 820,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $coupon = Coupon::create([
            'code' => 'FLAT20',
            'title' => '20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        ShopMobileCheckout::create([
            'user_id' => $user->id,
            'checkout_ref' => '01OLDPENDINGTEST',
            'fingerprint' => 'old-coupon-fp',
            'coupon_id' => $coupon->id,
            'coupon_code' => 'FLAT20',
            'coupon_merchandise_discount' => 20,
            'coupon_shipping_discount' => 0,
            'source' => 'cart',
            'currency' => 'aed',
            'amount_minor' => 85000,
            'lines_json' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 820]],
            'shipping_json' => [],
            'subtotal_amount' => 820,
            'tax_amount' => 40,
            'tax_percent' => 5,
            'shipping_amount' => 10,
            'total_amount' => 850,
            'wallet_amount_applied' => 0,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/customers')) {
                return Http::response(['id' => 'cus_no_inherit'], 200);
            }
            if (str_contains($request->url(), 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_no_inherit',
                    'client_secret' => 'pi_no_inherit_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $response = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $this->authHeaders($user));

        $response->assertStatus(200);
        $response->assertJsonPath('data.order_total', 871);
        $response->assertJsonPath('data.amount_due', 871);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'payment_intents')
                && (int) ($request->data()['amount'] ?? 0) === 87100;
        });
    }

    public function test_payment_intent_order_summary_matches_order_summary_endpoint(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 100,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/customers')) {
                return Http::response(['id' => 'cus_match_summary'], 200);
            }
            if (str_contains($request->url(), 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_match_summary',
                    'client_secret' => 'pi_match_summary_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $headers = $this->authHeaders($user);
        $summary = $this->getJson('/api/shop/order-summary', $headers);
        $summary->assertOk();
        $expectedTotal = (float) $summary->json('data.total');

        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $headers);

        $pi->assertOk();
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_total'));
        $this->assertSame($expectedTotal, (float) $pi->json('data.amount_due'));
        $this->assertSame($expectedTotal, (float) $pi->json('data.order_summary.total'));
        $pi->assertJsonPath('data.order_summary.subtotal', 200);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($expectedTotal): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'payment_intents')
                && (int) ($request->data()['amount'] ?? 0) === (int) round($expectedTotal * 100);
        });
    }

    public function test_payment_intent_without_coupon_code_charges_full_total_after_prior_apply(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 820,
            'compare_at_price' => null,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

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

        $headers = $this->authHeaders($user);
        $this->postJson('/api/shop/coupons/apply', ['code' => 'FLAT20'], $headers)->assertOk();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/customers')) {
                return Http::response(['id' => 'cus_full'], 200);
            }
            if (str_contains($request->url(), 'payment_intents') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'pi_full',
                    'client_secret' => 'pi_full_secret',
                    'status' => 'requires_payment_method',
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $headers);

        $pi->assertOk();
        $this->assertSame(871.0, (float) $pi->json('data.order_total'));
        $this->assertNull($pi->json('data.order_summary.coupon_code'));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'payment_intents')
                && (int) ($request->data()['amount'] ?? 0) === 87100;
        });
    }

    public function test_payment_intent_discards_stale_live_pi_when_test_keys_are_active(): void
    {
        Config::set('services.stripe.secret', 'sk_test_dummy');
        Config::set('services.stripe.key', 'pk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create(['shipping_cost' => null]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 820,
            'status' => 'active',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        ShopMobileCheckout::create([
            'user_id' => $user->id,
            'fingerprint' => 'stale-live',
            'checkout_ref' => 'stale-live-ref',
            'stripe_payment_intent_id' => 'pi_3TipOMP3hJZSveSo00ENImrD',
            'stripe_account_fingerprint' => hash('sha256', 'sk_live_old|pk_live_old'),
            'source' => 'cart',
            'currency' => 'aed',
            'amount_minor' => 87100,
            'lines_json' => [],
            'shipping_json' => $this->shippingPayload(),
            'subtotal_amount' => 820,
            'tax_amount' => 41,
            'tax_percent' => 5,
            'shipping_amount' => 10,
            'total_amount' => 871,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_fresh'], 200);
            }
            if ($request->method() === 'GET' && str_contains($url, 'payment_intents/pi_3TipOMP3hJZSveSo00ENImrD')) {
                return Http::response([
                    'error' => [
                        'code' => 'resource_missing',
                        'message' => "No such payment_intent: 'pi_3TipOMP3hJZSveSo00ENImrD'",
                    ],
                ], 404);
            }
            if ($request->method() === 'POST' && str_contains($url, 'payment_intents') && ! str_contains($url, '/cancel')) {
                return Http::response([
                    'id' => 'pi_test_fresh',
                    'client_secret' => 'pi_test_fresh_secret',
                    'status' => 'requires_payment_method',
                    'amount' => 87100,
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected '.$url]], 500);
        });

        $headers = $this->authHeaders($user);
        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => $this->shippingPayload(),
        ], $headers);

        $pi->assertOk()
            ->assertJsonPath('data.payment_intent_id', 'pi_test_fresh')
            ->assertJsonPath('data.client_secret', 'pi_test_fresh_secret')
            ->assertJsonPath('data.stripe_mode', 'test');

        $this->assertDatabaseMissing('shop_mobile_checkouts', [
            'stripe_payment_intent_id' => 'pi_3TipOMP3hJZSveSo00ENImrD',
        ]);
        $this->assertDatabaseHas('shop_mobile_checkouts', [
            'stripe_payment_intent_id' => 'pi_test_fresh',
            'user_id' => $user->id,
        ]);
    }
}
