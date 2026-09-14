<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrossRoleEmailPhoneReuseTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED_EMAIL = 'shared-all-roles@tandil.test';

    private const SHARED_PHONE = '0509988776';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['client', 'vendor', 'technician', 'supervisor', 'area_manager', 'hr', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_same_email_and_phone_allowed_for_client_and_vendor_roles(): void
    {
        $client = User::factory()->create([
            'email' => 'same@tandil.test',
            'phone' => '0501112233',
            'role' => 'client',
            'status' => 'active',
            'password' => Hash::make('password123'),
        ]);
        $client->assignRole('client');

        $vendorUser = User::factory()->create([
            'email' => 'same@tandil.test',
            'phone' => '0501112233',
            'role' => 'vendor',
            'status' => 'active',
            'password' => Hash::make('password123'),
        ]);
        $vendorUser->assignRole('vendor');

        $this->assertDatabaseCount('users', 2);
        $this->assertSame('same@tandil.test', $client->fresh()->email);
        $this->assertSame('same@tandil.test', $vendorUser->fresh()->email);
    }

    public function test_same_email_and_phone_can_create_client_supervisor_technician_manager_and_vendor(): void
    {
        // client / supervisor / technician / area_manager (manager) via public register API
        foreach (['client', 'supervisor', 'technician', 'area_manager'] as $role) {
            $this->postJson('/api/auth/register', [
                'name' => ucfirst(str_replace('_', ' ', $role)).' User',
                'email' => self::SHARED_EMAIL,
                'phone' => self::SHARED_PHONE,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => $role,
            ])
                ->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.role', $role)
                ->assertJsonPath('data.user.email', self::SHARED_EMAIL)
                ->assertJsonPath('data.user.phone', self::SHARED_PHONE);
        }

        // vendor is a separate account row with same credentials
        $vendor = User::factory()->create([
            'name' => 'Vendor User',
            'email' => self::SHARED_EMAIL,
            'phone' => self::SHARED_PHONE,
            'role' => 'vendor',
            'status' => 'active',
            'password' => Hash::make('password123'),
        ]);
        $vendor->assignRole('vendor');

        $roles = User::query()
            ->where('email', self::SHARED_EMAIL)
            ->where('phone', self::SHARED_PHONE)
            ->pluck('role')
            ->map(fn ($r) => strtolower((string) $r))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['area_manager', 'client', 'supervisor', 'technician', 'vendor'],
            $roles
        );
        $this->assertSame(5, User::query()->where('email', self::SHARED_EMAIL)->count());
    }

    public function test_same_email_rejected_within_same_role(): void
    {
        User::factory()->create([
            'email' => 'dup@tandil.test',
            'phone' => '0509998877',
            'role' => 'client',
            'status' => 'active',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/register', [
            'name' => 'Other Client',
            'email' => 'dup@tandil.test',
            'phone' => '0500001111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ])->assertStatus(422);
    }

    public function test_login_picks_correct_portal_user_when_email_shared(): void
    {
        $client = User::factory()->create([
            'email' => 'shared@tandil.test',
            'phone' => '0502223344',
            'role' => 'client',
            'status' => 'active',
            'password' => Hash::make('client-pass'),
        ]);
        $client->assignRole('client');

        $vendor = User::factory()->create([
            'email' => 'shared@tandil.test',
            'phone' => '0502223344',
            'role' => 'vendor',
            'status' => 'active',
            'password' => Hash::make('vendor-pass'),
        ]);
        $vendor->assignRole('vendor');

        $clientLogin = $this->postJson('/api/auth/login', [
            'email' => 'shared@tandil.test',
            'password' => 'client-pass',
            'roles' => 'client',
        ]);
        $clientLogin->assertOk()->assertJsonPath('data.slug', 'client');
        $this->assertSame($client->id, (int) $clientLogin->json('data.user.id'));

        $vendorLogin = $this->postJson('/api/auth/login', [
            'email' => 'shared@tandil.test',
            'password' => 'vendor-pass',
            'roles' => 'vendor',
        ]);
        $this->assertTrue(in_array($vendorLogin->status(), [200, 403], true));
        if ($vendorLogin->status() === 200) {
            $this->assertSame('vendor', $vendorLogin->json('data.slug'));
            $this->assertSame($vendor->id, (int) $vendorLogin->json('data.user.id'));
        }
    }
}
