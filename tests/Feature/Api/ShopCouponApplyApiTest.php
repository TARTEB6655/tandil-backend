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
