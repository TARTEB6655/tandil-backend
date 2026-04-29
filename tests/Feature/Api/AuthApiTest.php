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

    public function test_register_technician_invalid_service_area_returns_available_areas(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Bad Area',
            'email' => 'bad.area@example.com',
            'phone' => '+971500007777',
            'service_area' => 'Current Location GPS String',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['available_areas']]);
        $this->assertNotEmpty($response->json('data.available_areas'));
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
            ->assertJsonPath('data.service_area', 'Dubai Marina');
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
}

