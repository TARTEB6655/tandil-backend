<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
            }
        } catch (\Throwable $e) {
            // keep test resilient when permission tables are unavailable
        }
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
        $response->assertJsonPath('data.role', 'technician');
        $response->assertJsonPath('data.user.email', 'tech.signup@example.com');
        $response->assertJsonPath('data.user.employee.region', 'Dubai Marina');
        $response->assertJsonPath('data.user.employee.service_areas.0', 'Dubai Marina');
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'email' => 'tech.signup@example.com',
            'role' => 'technician',
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

