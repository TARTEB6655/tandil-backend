<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    public function test_vendor_can_register_via_api(): void
    {
        $response = $this->postJson('/api/vendor/auth/register', [
            'business_name' => 'Green Farms',
            'owner_name' => 'Ali Vendor',
            'email' => 'vendor@test.com',
            'phone' => '+971500000001',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', VendorStatus::Pending->value);

        $this->assertDatabaseHas('vendors', ['status' => 'pending']);
        $this->assertDatabaseHas('vendor_profiles', ['business_name' => 'Green Farms']);
    }

    public function test_admin_can_approve_vendor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $user->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/admin/vendors/{$vendor->id}/approve", [
            'notes' => 'Looks good',
        ]);

        $response->assertOk()->assertJsonPath('data.vendor.status', VendorStatus::Approved->value);
    }

    public function test_approved_vendor_cannot_access_other_vendor_product(): void
    {
        $v1User = User::factory()->create(['role' => 'vendor']);
        $v1User->assignRole('vendor');
        $v1 = Vendor::create(['user_id' => $v1User->id, 'status' => VendorStatus::Approved->value, 'approved_at' => now()]);
        VendorProfile::create(['vendor_id' => $v1->id, 'business_name' => 'V1', 'owner_name' => 'A', 'email' => 'v1@test.com']);

        $v2User = User::factory()->create(['role' => 'vendor']);
        $v2User->assignRole('vendor');
        $v2 = Vendor::create(['user_id' => $v2User->id, 'status' => VendorStatus::Approved->value, 'approved_at' => now()]);
        VendorProfile::create(['vendor_id' => $v2->id, 'business_name' => 'V2', 'owner_name' => 'B', 'email' => 'v2@test.com']);

        $token = $v1User->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)->getJson('/api/vendor/products/99999')->assertStatus(404);
    }
}
