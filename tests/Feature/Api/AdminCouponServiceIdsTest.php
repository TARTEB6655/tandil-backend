<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCouponServiceIdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Role::class) && Schema::hasTable('roles')) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    public function test_update_coupon_with_service_ids_bracket_multipart(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $service = Service::factory()->create();
        $coupon = Coupon::create([
            'code' => 'SRVCPN',
            'title' => 'Service coupon',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'applies_to' => 'all',
            'catalog_scope' => 'products',
        ]);

        $token = $admin->createToken('test')->plainTextToken;
        $boundary = '----CouponSvcBoundary9';
        $body = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"applies_to\"\r\n\r\n"
            ."services\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"service_ids[]\"\r\n\r\n"
            ."{$service->id}\r\n"
            ."--{$boundary}--\r\n";

        $this->call(
            'PUT',
            '/api/admin/coupons/'.$coupon->id,
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            ],
            $body
        )
            ->assertOk()
            ->assertJsonPath('data.applies_to', 'services')
            ->assertJsonPath('data.service_ids', [$service->id]);

        $coupon->refresh();
        $this->assertSame('services', $coupon->applies_to);
        $this->assertSame([$service->id], $coupon->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_create_coupon_with_service_ids_json_string(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $service = Service::factory()->create();

        $this->post('/api/admin/coupons', [
            'code' => 'SRVNEW',
            'title' => 'New service coupon',
            'discount_type' => 'percentage',
            'discount_value' => '5',
            'min_order_amount' => '0',
            'is_active' => '1',
            'applies_to' => 'services',
            'service_ids' => '['.$service->id.']',
            'category_ids' => '[]',
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.service_ids', [$service->id]);
    }
}
