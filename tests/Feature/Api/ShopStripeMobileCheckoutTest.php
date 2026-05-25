<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopStripeMobileCheckoutTest extends TestCase
{
    use RefreshDatabase;

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
}
