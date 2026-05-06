<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Area;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Smoke tests for shop payment routes: gateways, payment-methods, checkout/start,
 * PayPal capture (placeholder when no API keys), Stripe webhook signature.
 */
class ShopPaymentApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function clientAuthHeaders(User $user): array
    {
        $token = $user->createToken('smoke')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    public function test_get_payment_gateways_returns_json_success(): void
    {
        $response = $this->getJson('/api/shop/payment-gateways');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Payment gateways retrieved.');
        $response->assertJsonStructure([
            'data' => [
                ['id', 'type', 'label', 'enabled'],
                ['id', 'type', 'label', 'enabled'],
            ],
        ]);
    }

    public function test_get_shop_refund_policy_returns_configured_policy(): void
    {
        Setting::set('refund_partial_percent', '40', 'number', 'payment');
        $response = $this->getJson('/api/shop/refund-policy');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.rules.1.refund_percent', 40);
    }

    public function test_get_checkout_payment_methods_requires_authentication(): void
    {
        $response = $this->getJson('/api/shop/checkout/payment-methods');

        $response->assertStatus(401);
    }

    public function test_get_checkout_payment_methods_succeeds_for_client(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $response = $this->getJson('/api/shop/checkout/payment-methods', $this->clientAuthHeaders($user));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Payment methods retrieved.');
        $response->assertJsonCount(2, 'data.methods');
    }

    public function test_post_checkout_start_validation_error_when_missing_fields(): void
    {
        $response = $this->postJson('/api/shop/checkout/start', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    public function test_post_checkout_start_stripe_returns_422_when_stripe_not_usable(): void
    {
        Config::set('services.stripe.secret', '');
        Setting::set('stripe_enabled', '0');

        $user = User::factory()->create(['role' => 'client']);

        $response = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'stripe',
            'address_id' => null,
            'full_name' => 'Test User',
            'phone_number' => '+971501234567',
            'street_address' => 'Street 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
            'items' => [],
        ], $this->clientAuthHeaders($user));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonFragment(['message' => 'Stripe is not enabled or not configured.']);
    }

    public function test_post_checkout_start_paypal_guest_returns_201_with_placeholder_when_paypal_enabled(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => 'guest@example.com',
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
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.gateway', 'paypal');
        $response->assertJsonPath('data.order_id', Order::query()->latest('id')->first()->id);
        $this->assertNotEmpty($response->json('data.paypal_order_id'));
        $this->assertNotEmpty($response->json('data.approval_url'));
    }

    public function test_post_checkout_start_blocks_when_resolved_area_is_inactive(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        Area::factory()->create([
            'name' => 'Dubai City',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => false,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => 'guest@example.com',
            'full_name' => 'Guest',
            'phone_number' => '+971501234567',
            'street_address' => 'Road 1',
            'city' => 'Dubai',
            'country' => 'UAE',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Currently this area is not operational. Please try sometime.');
    }

    public function test_post_paypal_capture_marks_order_paid_with_placeholder_capture(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 5.00,
            'status' => 'active',
        ]);

        $start = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => 'g2@example.com',
            'full_name' => 'G2',
            'phone_number' => '+971501111111',
            'street_address' => 'S',
            'city' => 'DXB',
            'country' => 'UAE',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $start->assertStatus(201);
        $orderId = $start->json('data.order_id');
        $paypalOrderId = $start->json('data.paypal_order_id');

        $capture = $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $paypalOrderId,
            'order_id' => $orderId,
        ], ['Accept' => 'application/json']);

        $capture->assertOk();
        $capture->assertJsonPath('success', true);
        $capture->assertJsonPath('message', 'Payment captured.');

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_logged_in_paypal_capture_saves_vaulted_payment_method(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 7.00,
            'status' => 'active',
        ]);

        $start = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'full_name' => 'Client A',
            'phone_number' => '+971501111112',
            'street_address' => 'S',
            'city' => 'DXB',
            'country' => 'UAE',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], $this->clientAuthHeaders($user));

        $start->assertStatus(201);
        $capture = $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $start->json('data.paypal_order_id'),
            'order_id' => $start->json('data.order_id'),
        ], $this->clientAuthHeaders($user));
        $capture->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_payment_methods', [
            'user_id' => $user->id,
            'gateway' => 'paypal',
        ]);
    }

    public function test_logged_in_checkout_can_use_saved_paypal_method_without_approval_url(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $user = User::factory()->create(['role' => 'client']);
        $saved = UserPaymentMethod::query()->create([
            'user_id' => $user->id,
            'gateway' => 'paypal',
            'provider_method_id' => 'PMT_TEST_123',
            'label' => 'PayPal test@example.com',
            'is_default' => true,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 11.00,
            'status' => 'active',
        ]);

        $start = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'paypal_payment_method_id' => $saved->id,
            'full_name' => 'Client B',
            'phone_number' => '+971501111113',
            'street_address' => 'S',
            'city' => 'DXB',
            'country' => 'UAE',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], $this->clientAuthHeaders($user));

        $start->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'PayPal payment captured using saved method.')
            ->assertJsonPath('data.saved_payment_method_id', $saved->id)
            ->assertJsonPath('data.status', 'paid');
        $this->assertNull($start->json('data.approval_url'));
    }

    public function test_post_paypal_capture_returns_422_when_order_id_does_not_match_reference(): void
    {
        $o1 = Order::factory()->create([
            'payment_method' => 'paypal',
            'payment_reference' => 'PP_REAL_1',
            'payment_status' => 'pending',
        ]);
        Order::factory()->create([
            'payment_method' => 'paypal',
            'payment_reference' => 'PP_REAL_2',
            'payment_status' => 'pending',
        ]);

        $response = $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => 'PP_REAL_2',
            'order_id' => $o1->id,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_post_stripe_webhook_rejects_bad_signature(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test_secret');

        $response = $this->postJson('/api/shop/webhooks/stripe', ['type' => 'checkout.session.completed'], [
            'Accept' => 'application/json',
            'Stripe-Signature' => 'invalid',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Invalid signature');
    }

    public function test_post_stripe_webhook_accepts_valid_signature_and_marks_order_paid(): void
    {
        $secret = 'whsec_test_secret';
        Config::set('services.stripe.webhook_secret', $secret);

        $order = Order::factory()->create([
            'payment_method' => 'stripe',
            'payment_status' => 'pending',
        ]);

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ]);
        $this->assertIsString($payload);

        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $sigHeader = 't='.$timestamp.',v1='.$signature;

        $response = $this->call(
            'POST',
            '/api/shop/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $sigHeader,
            ],
            $payload
        );

        $response->assertOk();
        $response->assertJsonPath('received', true);
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
    }
}
