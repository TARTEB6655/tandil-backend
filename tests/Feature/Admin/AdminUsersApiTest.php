<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUsersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
    }

    public function test_admin_users_list_excludes_vendors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $vendor = User::factory()->create(['role' => 'vendor', 'name' => 'Ali Vendor', 'email' => 'vendor@test.com']);
        $vendor->assignRole('vendor');

        $worker = User::factory()->create(['role' => 'technician', 'name' => 'Field Worker', 'email' => 'worker@test.com']);
        $worker->assignRole('technician');

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/admin/users?category=all');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $emails = collect($response->json('data.data'))->pluck('email')->all();
        $this->assertContains('worker@test.com', $emails);
        $this->assertNotContains('vendor@test.com', $emails);
    }

    public function test_admin_users_statistics_excludes_vendors_from_all_users_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $vendor = User::factory()->create(['role' => 'vendor']);
        $vendor->assignRole('vendor');

        User::factory()->create(['role' => 'technician'])->assignRole('technician');

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/users/statistics')
            ->assertOk()
            ->assertJsonPath('data.all_users', 2) // admin + technician, not vendor
            ->assertJsonPath('data.workers', 1);
    }
}
