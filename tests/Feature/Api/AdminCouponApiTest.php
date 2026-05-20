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
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Coupons loaded.')
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'total']]);

        $save10 = collect($response->json('data'))->firstWhere('code', 'SAVE10');
        $this->assertNotNull($save10);
        $this->assertSame('10% off', $save10['title']);
        $this->assertSame('percentage', $save10['discount_type']);
        $this->assertSame(3, $save10['usage_limit_per_user']);
        $this->assertSame('all', $save10['applies_to']);
        $this->assertSame('products', $save10['catalog_scope']);
        $this->assertTrue($save10['is_active']);
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
            'applies_to' => 'all',
            'catalog_scope' => 'products',
            'category_ids' => '[]',
        ], $this->adminHeaders($admin))
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('message', 'Coupon created.');
    }

    public function test_admin_update_rejects_code_change(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $coupon = Coupon::create([
            'code' => 'OLDCODE',
            'title' => 'Old',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'both',
        ]);

        $this->putJson('/api/admin/coupons/'.$coupon->id, [
            'code' => 'NEWCODE',
            'title' => 'Updated',
        ], $this->adminHeaders($admin))
            ->assertStatus(422);
    }
}
