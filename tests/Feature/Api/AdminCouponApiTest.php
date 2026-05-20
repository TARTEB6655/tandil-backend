<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\User;
use Database\Seeders\DemoCouponsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCouponApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(User $user): array
    {
        $token = $user->createToken('admin-coupon-list')->plainTextToken;

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
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function test_admin_list_matches_mobile_coupons_screen(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->seed(DemoCouponsSeeder::class);

        $response = $this->getJson('/api/admin/coupons', $this->adminHeaders($admin))
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(4, count($data));

        $save10 = collect($data)->firstWhere('code', 'SAVE10');
        $this->assertNotNull($save10);
        $this->assertSame('10% off', $save10['title']);
        $this->assertSame('percentage', $save10['discount_type']);
        $this->assertSame(10.0, (float) $save10['discount_value']);
        $this->assertSame(50.0, (float) $save10['min_order_amount']);
        $this->assertSame(30.0, (float) $save10['max_discount_amount']);
        $this->assertTrue($save10['is_active']);

        $freeShip = collect($data)->firstWhere('code', 'FREESHIP');
        $this->assertNotNull($freeShip);
        $this->assertSame('free_shipping', $freeShip['discount_type']);
        $this->assertNull($freeShip['discount_value']);
        $this->assertSame('Free shipping', $freeShip['title']);
    }

    public function test_admin_create_coupon_via_form_fields(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->post('/api/admin/coupons', [
            'code' => 'save10',
            'title' => '10% off',
            'description' => '10% off orders over AED 50 (max AED 30 off).',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'min_order_amount' => '50',
            'max_discount_amount' => '30',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'is_active' => '1',
            'usage_limit' => '',
            'usage_limit_per_user' => '',
        ], $this->adminHeaders($admin))
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.title', '10% off')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE10',
            'discount_type' => 'percentage',
        ]);
    }

    public function test_admin_create_free_shipping_without_discount_value(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->post('/api/admin/coupons', [
            'code' => 'FREESHIP2',
            'title' => 'Free shipping',
            'description' => 'Free shipping on orders over AED 75.',
            'discount_type' => 'free_shipping',
            'min_order_amount' => '75',
            'is_active' => '1',
        ], $this->adminHeaders($admin))
            ->assertStatus(201)
            ->assertJsonPath('data.discount_type', 'free_shipping')
            ->assertJsonPath('data.discount_value', null);
    }

    public function test_admin_update_coupon_partial_form(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $coupon = Coupon::create([
            'code' => 'OLDCODE',
            'title' => 'Old title',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
        ]);

        $this->put('/api/admin/coupons/'.$coupon->id, [
            'title' => 'Updated title',
            'is_active' => '0',
        ], $this->adminHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.code', 'OLDCODE');
    }
}
