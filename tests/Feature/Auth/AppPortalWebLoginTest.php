<?php

namespace Tests\Feature\Auth;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppPortalWebLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_vendor_credentials_fail_on_admin_portal_with_vendor_suggestion(): void
    {
        $user = $this->createApprovedVendor('vendor@example.com', 'secret12');

        $response = $this->withSession(['app_portal' => 'admin'])
            ->post(route('app-portal.login.submit'), [
                'email' => $user->email,
                'password' => 'secret12',
                'portal' => 'admin',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $response->assertSessionHas('suggested_portal', 'vendor');
        $this->assertGuest();
    }

    public function test_approved_vendor_can_login_via_vendor_portal(): void
    {
        $user = $this->createApprovedVendor('vendor-web@test.com', 'secret12');

        $response = $this->withSession(['app_portal' => 'vendor'])
            ->post(route('app-portal.login.submit'), [
                'email' => $user->email,
                'password' => 'secret12',
                'portal' => 'vendor',
            ]);

        $response->assertRedirect(route('vendor.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_vendor_login_route_presets_vendor_portal(): void
    {
        $this->get('/vendor/login')
            ->assertRedirect('/app-portal/login?portal=vendor');
    }

    private function createApprovedVendor(string $email, string $password): User
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email' => $email,
            'password' => Hash::make($password),
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
            'business_name' => 'Test Vendor',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        return $user;
    }
}
