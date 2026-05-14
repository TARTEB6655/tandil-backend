<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
                Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
                Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
                Role::firstOrCreate(['name' => 'area_manager', 'guard_name' => 'web']);
            }
        } catch (\Throwable $e) {
            // keep test resilient when permission tables are unavailable
        }

        $this->area = Area::factory()->create(['name' => 'Dubai Marina']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        try {
            if (method_exists($supervisor, 'assignRole')) {
                $supervisor->assignRole('supervisor');
            }
        } catch (\Throwable $e) {
            // no-op
        }
        $supervisor->supervisedAreas()->attach($this->area->id);
    }

    public function test_register_technician_success(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech Signup',
            'email' => 'tech.signup@example.com',
            'phone' => '+971500009999',
            'service_area' => 'Dubai Marina',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.service_area', 'Dubai Marina');
        $this->assertNotEmpty($response->json('data.request_id'));

        $this->assertDatabaseMissing('users', [
            'email' => 'tech.signup@example.com',
        ]);
        $this->assertDatabaseHas('technician_signup_requests', [
            'email' => 'tech.signup@example.com',
            'phone' => '+971500009999',
            'status' => 'pending',
            'area_id' => $this->area->id,
        ]);
    }

    public function test_register_technician_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech Signup',
            'email' => 'tech.signup@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'phone',
            'service_area',
            'password',
        ]);
    }

    public function test_technician_signup_areas_returns_supervised_zones(): void
    {
        $response = $this->getJson('/api/auth/technician-signup-areas');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertSame($this->area->id, $rows[0]['id']);

        $alias = $this->getJson('/api/technician-signup-areas');
        $alias->assertStatus(200)->assertJsonPath('success', true);
        $this->assertEquals($rows, $alias->json('data'));
    }

    public function test_register_technician_success_with_area_id(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech By Area Id',
            'email' => 'tech.byareaid@example.com',
            'phone' => '+971500008888',
            'area_id' => $this->area->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_area', 'Dubai Marina');

        $this->assertDatabaseHas('technician_signup_requests', [
            'email' => 'tech.byareaid@example.com',
            'area_id' => $this->area->id,
        ]);
    }

    public function test_register_technician_accepts_any_free_text_location_without_zone_match(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'World Tech',
            'email' => 'world.tech@example.com',
            'phone' => '+971500007777',
            'service_area' => 'San Francisco, CA, United States',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_area', 'San Francisco, CA, United States')
            ->assertJsonPath('data.area_id', null);

        $this->assertDatabaseHas('technician_signup_requests', [
            'email' => 'world.tech@example.com',
            'area_id' => null,
        ]);
    }

    public function test_register_technician_resolves_zone_name_inside_map_style_address(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Map Style',
            'email' => 'map.style@example.com',
            'phone' => '+971500006666',
            'service_area' => 'Plot 12, Dubai Marina, United Arab Emirates',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.area_id', $this->area->id)
            ->assertJsonPath('data.service_area', 'Plot 12, Dubai Marina, United Arab Emirates');
    }

    public function test_register_technician_rejects_duplicate_email_or_phone(): void
    {
        User::factory()->create([
            'email' => 'exists.tech@example.com',
            'phone' => '+971500001111',
            'role' => 'technician',
        ]);

        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech Signup',
            'email' => 'exists.tech@example.com',
            'phone' => '+971500001111',
            'service_area' => 'Abu Dhabi',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_login_resolves_portal_from_spatie_when_users_role_column_is_stale(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'portal-mismatch@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['area_manager']);

        $this->postJson('/api/auth/login', [
            'email' => 'portal-mismatch@example.com',
            'password' => 'password',
            'roles' => 'area_manager',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'area_manager');
    }

    public function test_login_requires_roles_parameter(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'any@example.com',
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_app_login_roles_returns_ordered_slugs(): void
    {
        $response = $this->getJson('/api/auth/app-roles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertCount(6, $rows);
        $this->assertSame('client', $rows[0]['slug']);
        $this->assertSame('Client (Customer)', $rows[0]['role']);
        $this->assertSame('Client (Customer)', $rows[0]['title']);
        $this->assertStringContainsString('Subscribe to plans', $rows[0]['description']);
        $this->assertSame($rows[0]['description'], $rows[0]['subtitle']);
        $this->assertArrayHasKey('icon', $rows[0]);
        $this->assertSame('admin', $rows[5]['slug']);
    }

    public function test_login_succeeds_with_roles_slug(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'roles-param@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['client']);

        $this->postJson('/api/auth/login', [
            'email' => 'roles-param@example.com',
            'password' => 'password',
            'roles' => 'client',
        ])->assertStatus(200)
            ->assertJsonPath('data.slug', 'client');
    }

    public function test_login_rejects_invalid_roles_value(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'any@example.com',
            'password' => 'password',
            'roles' => 'not-a-real-role',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_login_roles_must_match_account(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'roles-wrong@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['client']);

        $this->postJson('/api/auth/login', [
            'email' => 'roles-wrong@example.com',
            'password' => 'password',
            'roles' => 'admin',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
