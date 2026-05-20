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

class ShopCouponApiTest extends TestCase
{
    use RefreshDatabase;

    private function clientHeaders(User $user): array
    {
        $token = $user->createToken('coupon-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    private function adminHeaders(User $user): array
    {
        $token = $user->createToken('admin-coupon')->plainTextToken;

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
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function test_validate_coupon_returns_contract_shape(): void
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
            'min_order_amount' => 50,
            'max_discount_amount' => 30,
            'starts_at' => now()->subDay()->toDateString(),
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'both',
        ]);

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 100,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $this->clientHeaders($client))->assertStatus(201);

        $this->postJson('/api/shop/coupons/validate', [
            'code' => 'save10',
            'subtotal' => 100,
            'catalog_discount' => 0,
        ], $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.coupon_discount', 10)
            ->assertJsonPath('data.free_shipping', false);

        $this->getJson('/api/shop/order-summary?coupon_code=SAVE10', $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.coupon_code', 'SAVE10')
            ->assertJsonPath('data.coupon_discount', 10);
    }

    public function test_validate_respects_catalog_discount_for_min_order(): void
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
            'min_order_amount' => 50,
            'max_discount_amount' => 30,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'both',
        ]);

        $this->postJson('/api/shop/coupons/validate', [
            'code' => 'SAVE10',
            'subtotal' => 55,
            'catalog_discount' => 10,
        ], $this->clientHeaders($client))
            ->assertStatus(422);
    }

    public function test_inactive_coupon_returns_422(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        Coupon::create([
            'code' => 'EXPIRED',
            'title' => 'Off',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'min_order_amount' => 0,
            'is_active' => false,
            'applies_to' => 'all',
            'catalog_scope' => 'both',
        ]);

        $this->postJson('/api/shop/coupons/validate', ['code' => 'EXPIRED'], $this->clientHeaders($client))
            ->assertStatus(422);
    }

    public function test_admin_can_create_coupon_json(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->postJson('/api/admin/coupons', [
            'code' => 'NEWCODE',
            'title' => 'Test',
            'discount_type' => 'fixed_amount',
            'discount_value' => 15,
            'min_order_amount' => 50,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'both',
            'category_ids' => [],
        ], $this->adminHeaders($admin))
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'NEWCODE');

        $this->assertDatabaseHas('coupons', ['code' => 'NEWCODE']);
    }
}
