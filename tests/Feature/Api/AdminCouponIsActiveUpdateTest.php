<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCouponIsActiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Role::class) && Schema::hasTable('roles')) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    public function test_update_is_active_zero_via_form_data(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $coupon = Coupon::create([
            'code' => 'ACTIVE1',
            'title' => 'Active coupon',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $token = $admin->createToken('test')->plainTextToken;
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];

        $this->put('/api/admin/coupons/'.$coupon->id, [
            'title' => 'Active coupon',
            'is_active' => '0',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_update_is_active_one_via_form_data(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $coupon = Coupon::create([
            'code' => 'OFF1',
            'title' => 'Off coupon',
            'discount_type' => 'fixed_amount',
            'discount_value' => 5,
            'min_order_amount' => 0,
            'is_active' => false,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $token = $admin->createToken('test')->plainTextToken;
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];

        $this->put('/api/admin/coupons/'.$coupon->id, ['is_active' => '1'], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($coupon->fresh()->is_active);
    }
}
