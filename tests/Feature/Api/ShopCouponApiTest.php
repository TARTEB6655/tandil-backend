<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Service;
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
            'catalog_scope' => 'products',
        ]);

        $cat = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $cat->id,
            'price' => 100,
            'compare_at_price' => null,
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
            'catalog_scope' => 'products',
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
            'catalog_scope' => 'products',
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
            'category_ids' => [],
            'service_ids' => [],
        ], $this->adminHeaders($admin))
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'NEWCODE');

        $this->assertDatabaseHas('coupons', ['code' => 'NEWCODE']);
    }

    public function test_browse_lists_all_and_category_coupons_for_category(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $catA = Category::factory()->create(['name' => 'Vegetables']);
        $catB = Category::factory()->create(['name' => 'Fruits']);

        $allCoupon = Coupon::create([
            'code' => 'ALL5',
            'title' => 'Store wide',
            'discount_type' => 'fixed_amount',
            'discount_value' => 5,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $catCoupon = Coupon::create([
            'code' => 'CAT10',
            'title' => 'Category demo',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'categories',
            'catalog_scope' => 'products',
        ]);
        $catCoupon->categories()->sync([$catA->id]);

        $otherCatCoupon = Coupon::create([
            'code' => 'OTHER',
            'title' => 'Other cat',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'categories',
            'catalog_scope' => 'products',
        ]);
        $otherCatCoupon->categories()->sync([$catB->id]);

        $this->getJson('/api/shop/coupons/browse?category_id='.$catA->id, $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['code' => 'ALL5'])
            ->assertJsonFragment(['code' => 'CAT10'])
            ->assertJsonFragment(['scope_label' => 'Category: Vegetables'])
            ->assertJsonMissing(['code' => 'OTHER']);
    }

    public function test_browse_rejects_both_category_and_service_id(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $cat = Category::factory()->create();
        $service = Service::factory()->create();

        $this->getJson(
            '/api/shop/coupons/browse?category_id='.$cat->id.'&service_id='.$service->id,
            $this->clientHeaders($client)
        )->assertStatus(422);
    }

    public function test_browse_with_all_lists_only_storewide_coupons(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $cat = Category::factory()->create();

        Coupon::create([
            'code' => 'ALLONLY',
            'title' => 'All products',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $catCoupon = Coupon::create([
            'code' => 'CATONLY',
            'title' => 'Category only',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'categories',
            'catalog_scope' => 'products',
        ]);
        $catCoupon->categories()->sync([$cat->id]);

        $this->getJson('/api/shop/coupons/browse?all=1', $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.scope', 'all')
            ->assertJsonFragment(['code' => 'ALLONLY'])
            ->assertJsonMissing(['code' => 'CATONLY']);
    }

    public function test_browse_rejects_all_with_category_or_service(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $cat = Category::factory()->create();
        $service = Service::factory()->create();

        $this->getJson(
            '/api/shop/coupons/browse?all=1&category_id='.$cat->id,
            $this->clientHeaders($client)
        )->assertStatus(422);

        $this->getJson(
            '/api/shop/coupons/browse?all=1&service_id='.$service->id,
            $this->clientHeaders($client)
        )->assertStatus(422);
    }

    public function test_browse_lists_service_coupons_for_service(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $service = Service::factory()->create(['name' => 'Local slaughter']);

        $svcCoupon = Coupon::create([
            'code' => 'SVC20',
            'title' => 'Service offer',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'services',
            'catalog_scope' => 'services',
        ]);
        $svcCoupon->services()->sync([$service->id]);

        $this->getJson('/api/shop/coupons/browse?service_id='.$service->id, $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonFragment(['code' => 'SVC20'])
            ->assertJsonPath('data.0.scope_label', 'Service: Local slaughter');
    }

    public function test_checkout_offers_splits_available_and_not_eligible(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $cat = Category::factory()->create(['name' => 'Summer']);

        Coupon::create([
            'code' => 'DEMO5',
            'title' => 'Small order',
            'description' => 'AED 5 off (small orders)',
            'discount_type' => 'fixed_amount',
            'discount_value' => 5,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        Coupon::create([
            'code' => 'SAVE10',
            'title' => '10% off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 50,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $catCoupon = Coupon::create([
            'code' => 'CAT10',
            'title' => '10% category demo',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'categories',
            'catalog_scope' => 'products',
        ]);
        $catCoupon->categories()->sync([$cat->id]);

        $this->postJson('/api/shop/coupons/checkout-offers', [
            'subtotal' => 26.10,
            'catalog_discount' => 0,
            'cart_category_ids' => [],
            'cart_catalog' => 'products',
        ], $this->clientHeaders($client))
            ->assertOk()
            ->assertJsonPath('data.available_count', 1)
            ->assertJsonPath('data.available_for_order.0.code', 'DEMO5')
            ->assertJsonPath('data.available_for_order.0.applies_to_label', 'All products')
            ->assertJsonFragment([
                'code' => 'SAVE10',
                'ineligible_reason' => 'Minimum order is 50 AED after discounts.',
            ])
            ->assertJsonFragment([
                'code' => 'CAT10',
                'ineligible_reason' => 'This offer applies to specific categories. Your cart does not include eligible category items.',
            ])
            ->assertJsonFragment(['code' => 'CAT10', 'scope_summary' => 'Categories: Summer']);
    }
}
