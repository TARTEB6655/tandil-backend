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

