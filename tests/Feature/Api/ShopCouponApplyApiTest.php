<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShopMobileCheckout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopCouponApplyApiTest extends TestCase
{
    use RefreshDatabase;

    private function clientHeaders(User $user): array
    {
        $token = $user->createToken('coupon-apply')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function test_apply_coupon_returns_checkout_summary_with_vat_alias(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'SAVE10',
            'title' => '10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'max_discount_amount' => 30,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 300,
            'compare_at_price' => null,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $this->clientHeaders($client))->assertStatus(201);

        $response = $this->postJson('/api/shop/coupons/apply', [
            'code' => 'SAVE10',
        ], $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.discount_type', 'percentage')
            ->assertJsonPath('data.discount_value', 10)
            ->assertJsonPath('data.coupon_discount', 30)
            ->assertJsonStructure([
                'data' => [
                    'order_summary' => [
                        'subtotal',
                        'tax',
                        'vat',
                        'vat_percent',
                        'total',
                        'coupon_code',
                    ],
                ],
            ]);

        $summary = $response->json('data.order_summary');
        $this->assertSame(300.0, (float) $summary['subtotal']);
        $this->assertSame(30.0, (float) $summary['coupon_discount']);
        $this->assertSame((float) $summary['tax'], (float) $summary['vat']);
        $this->assertLessThan(315.0, (float) $summary['total']);
        $this->assertSame('10% OFF', $response->json('data.discount_label'));
    }

    public function test_apply_fixed_amount_coupon_with_code_only(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'FLAT20',
            'title' => 'AED 20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 100,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $this->clientHeaders($client));

        $this->postJson('/api/shop/coupons/apply', ['code' => 'FLAT20'], $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.discount_type', 'fixed_amount')
            ->assertJsonPath('data.discount_value', 20)
            ->assertJsonPath('data.coupon_discount', 20)
            ->assertJsonPath('data.discount_label', '20 AED OFF')
            ->assertJsonPath('data.order_summary.coupon_code', 'FLAT20');
    }

    public function test_apply_flat20_passes_when_subtotal_meets_min_despite_catalog_discount(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'FLAT20',
            'title' => 'AED 20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 100,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $this->postJson('/api/shop/coupons/apply', [
            'code' => 'FLAT20',
            'subtotal' => 125,
            'catalog_discount' => 30,
            'cart_catalog' => 'products',
        ], $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.code', 'FLAT20')
            ->assertJsonPath('data.coupon_discount', 20)
            ->assertJsonPath('data.order_summary.subtotal', 125)
            ->assertJsonPath('data.order_summary.coupon_discount', 20);
    }

    public function test_apply_ignores_client_subtotal_when_server_cart_meets_min(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'FLAT20',
            'title' => 'AED 20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 100,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 125,
            'compare_at_price' => 150,
            'status' => 'active',
        ]);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $this->clientHeaders($client))->assertStatus(201);

        $this->postJson('/api/shop/coupons/apply', [
            'code' => 'FLAT20',
            'subtotal' => 50,
            'catalog_discount' => 40,
        ], $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.code', 'FLAT20')
            ->assertJsonPath('data.order_summary.subtotal', 125)
            ->assertJsonPath('data.coupon_discount', 20);
    }

    public function test_apply_auto_updates_pending_stripe_payment_intent(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        Config::set('services.stripe.secret', 'sk_test_dummy');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'FLAT20',
            'title' => 'AED 20 off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 20,
            'min_order_amount' => 100,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 125,
            'compare_at_price' => null,
            'status' => 'active',
        ]);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $this->clientHeaders($client))->assertStatus(201);

        ShopMobileCheckout::create([
            'user_id' => $client->id,
            'checkout_ref' => 'test-'.Str::uuid(),
            'stripe_payment_intent_id' => 'pi_test_flat20',
            'source' => 'cart',
            'currency' => 'aed',
            'amount_minor' => 14125,
            'lines_json' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 125]],
            'shipping_json' => ['full_name' => 'Test'],
            'subtotal_amount' => 125,
            'tax_amount' => 6.25,
            'tax_percent' => 5,
            'shipping_amount' => 10,
            'total_amount' => 141.25,
        ]);

        $stripeUpdateAmount = null;
        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$stripeUpdateAmount) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, 'payment_intents/pi_test_flat20')) {
                return Http::response([
                    'id' => 'pi_test_flat20',
                    'amount' => $stripeUpdateAmount ?? 12025,
                    'status' => 'requires_payment_method',
                    'client_secret' => 'pi_test_flat20_secret_updated',
                ], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, 'payment_intents/pi_test_flat20') && ! str_contains($url, '/cancel')) {
                $stripeUpdateAmount = (int) ($request->data()['amount'] ?? 0);

                return Http::response([
                    'id' => 'pi_test_flat20',
                    'client_secret' => 'pi_test_flat20_secret_updated',
                    'status' => 'requires_payment_method',
                    'amount' => $stripeUpdateAmount,
                ], 200);
            }

            return Http::response(['error' => ['message' => 'unexpected '.$url]], 500);
        });

        $this->postJson('/api/shop/coupons/apply', ['code' => 'FLAT20'], $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.payment.payment_intent_id', 'pi_test_flat20')
            ->assertJsonPath('data.payment.client_secret', 'pi_test_flat20_secret_updated')
            ->assertJsonPath('data.payment.order_total', 120.25)
            ->assertJsonPath('data.payment.amount_due', 120.25)
            ->assertJsonPath('data.payment.amount_minor', 12025)
            ->assertJsonPath('data.reinitialize_payment_sheet', true);

        $this->assertSame(12025, $stripeUpdateAmount);
    }

    public function test_apply_invalid_coupon_returns_422(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $this->postJson('/api/shop/coupons/apply', [
            'code' => 'NOPE',
            'subtotal' => 100,
        ], $this->clientHeaders($client))
            ->assertStatus(422);
    }

    public function test_order_summary_wallet_toggle_keeps_applied_coupon_when_code_omitted(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        Setting::set('shop_shipping_amount', '120', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $client = User::factory()->create(['role' => 'client', 'wallet_balance' => 137.02]);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'SAVE10',
            'title' => '10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'max_discount_amount' => null,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $cat = Category::factory()->create();
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
        ], $this->clientHeaders($client))->assertStatus(201);

        $apply = $this->postJson('/api/shop/coupons/apply', [
            'code' => 'SAVE10',
        ], $this->clientHeaders($client))->assertOk();

        $appliedSummary = $apply->json('data.order_summary');
        $withCouponTotal = (float) $appliedSummary['total'];
        $couponDiscount = (float) $appliedSummary['coupon_discount'];
        $this->assertGreaterThan(0, $couponDiscount);

        // App often refreshes summary with use_wallet only (no coupon_code) when wallet is toggled on.
        $walletRefresh = $this->getJson('/api/shop/order-summary?use_wallet=1', $this->clientHeaders($client))
            ->assertOk();

        $summary = $walletRefresh->json('data');
        $this->assertSame($withCouponTotal, (float) $summary['total']);
        $this->assertSame($couponDiscount, (float) $summary['coupon_discount']);
        $this->assertSame('SAVE10', $summary['coupon_code']);
        $this->assertSame(137.02, (float) $summary['wallet_amount_applied']);
        $this->assertSame(round($withCouponTotal - 137.02, 2), (float) $summary['amount_due']);
        $this->assertLessThan($withCouponTotal, (float) $summary['amount_due']);
    }

    public function test_plain_order_summary_after_apply_does_not_silently_keep_coupon(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        Setting::set('shop_shipping_amount', '120', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $client = User::factory()->create(['role' => 'client']);
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

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 1438.50,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $this->clientHeaders($client))->assertStatus(201);

        $discounted = (float) $this->postJson('/api/shop/coupons/apply', ['code' => 'SAVE10'], $this->clientHeaders($client))
            ->assertOk()
            ->json('data.order_summary.total');

        $plain = $this->getJson('/api/shop/order-summary', $this->clientHeaders($client))->assertOk();
        $fullTotal = (float) $plain->json('data.total');

        $this->assertGreaterThan($discounted, $fullTotal);
        $this->assertSame(0.0, (float) $plain->json('data.coupon_discount'));
        $this->assertNull($plain->json('data.coupon_code'));
    }

    public function test_remove_coupon_api_clears_discount_and_returns_full_total(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        Setting::set('shop_shipping_amount', '120', 'number', 'shop');
        Setting::set('shop_tax_percent', '5', 'number', 'shop');

        $client = User::factory()->create(['role' => 'client']);
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

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 1438.50,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $headers = $this->clientHeaders($client);
        $this->postJson('/api/shop/cart/add', ['product_id' => $product->id, 'quantity' => 1], $headers)->assertStatus(201);
        $this->postJson('/api/shop/coupons/apply', ['code' => 'SAVE10'], $headers)->assertOk();

        $remove = $this->postJson('/api/shop/coupons/remove', [], $headers)->assertOk();
        $summary = $remove->json('data.order_summary');

        $this->assertSame(0.0, (float) $summary['coupon_discount']);
        $this->assertArrayNotHasKey('coupon_code', $summary);
        $this->assertDatabaseMissing('shop_applied_checkout_coupons', ['user_id' => $client->id]);

        $plain = $this->getJson('/api/shop/order-summary', $headers)->assertOk();
        $this->assertSame((float) $summary['total'], (float) $plain->json('data.total'));
    }
}
