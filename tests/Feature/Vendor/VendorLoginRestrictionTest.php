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

class VendorLoginRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_pending_vendor_cannot_login_via_api(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email' => 'pending-vendor@test.com',
            'password' => Hash::make('secret12'),
            'status' => 'active',
        ]);
        $user->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Pending Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $this->postJson('/api/vendor/auth/login', [
            'email' => 'pending-vendor@test.com',
            'password' => 'secret12',
            'roles' => 'vendor',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_approved_vendor_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email' => 'approved-vendor@test.com',
            'password' => Hash::make('secret12'),
            'status' => 'active',
        ]);
        $user->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Approved Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $this->postJson('/api/vendor/auth/login', [
            'email' => 'approved-vendor@test.com',
            'password' => 'secret12',
            'roles' => 'vendor',
        ])
            ->assertOk()
            ->assertJsonPath('data.vendor.is_approved', true);
    }

    public function test_pending_vendor_token_cannot_access_vendor_profile_api(): void
    {
        $user = User::factory()->create(['role' => 'vendor', 'status' => 'active']);
        $user->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'X',
            'owner_name' => 'Y',
            'email' => $user->email,
        ]);

        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/vendor/profile')
            ->assertForbidden();
    }
}
