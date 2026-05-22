<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
}
