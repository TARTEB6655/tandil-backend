<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration
     */
    public function test_user_can_register()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'role',
                    'user' => ['id', 'name', 'email', 'role'],
                ],
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'client',
        ]);
    }

    /**
     * Test registration validation errors
     */
    public function test_registration_requires_valid_data()
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    /**
     * Test registration with duplicate email
     */
    public function test_registration_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test user login
     */
    public function test_user_can_login()
    {
        $user = $this->createCustomer([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'role',
                    'user',
                ],
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * Test login with invalid credentials
     */
    public function test_login_fails_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /**
     * Test login with inactive account
     */
    public function test_login_fails_with_inactive_account()
    {
        $user = $this->createCustomer([
            'email' => 'inactive@example.com',
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /**
     * Test user can view profile
     */
    public function test_user_can_view_profile()
    {
        $user = $this->createCustomer();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'role',
                    'user' => ['id', 'name', 'email'],
                ],
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * Test profile requires authentication
     */
    public function test_profile_requires_authentication()
    {
        $response = $this->getJson('/api/auth/profile');

        $response->assertStatus(401);
    }

    /**
     * Test user can logout
     */
    public function test_user_can_logout()
    {
        $user = $this->createCustomer();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test logout requires authentication
     */
    public function test_logout_requires_authentication()
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    /**
     * Test health check endpoint
     */
    public function test_health_check_endpoint()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'API is working']);
    }
}

