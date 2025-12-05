<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can list users
     */
    public function test_admin_can_list_users()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->createCustomer();
        $this->createTechnician();

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    /**
     * Test admin can create user
     */
    public function test_admin_can_create_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
            'status' => 'active',
        ]);

        $response->assertStatus(302); // Redirect after creation

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    /**
     * Test admin can view user
     */
    public function test_admin_can_view_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $user = $this->createCustomer();

        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200);
    }

    /**
     * Test admin can update user
     */
    public function test_admin_can_update_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $user = $this->createCustomer();

        $response = $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'client',
        ]);

        $response->assertStatus(302); // Redirect after update

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test admin can delete user
     */
    public function test_admin_can_delete_user()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $user = $this->createCustomer();

        $response = $this->deleteJson("/api/admin/users/{$user->id}");

        $response->assertStatus(302); // Redirect after delete

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * Test admin can list roles
     */
    public function test_admin_can_list_roles()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200);
    }

    /**
     * Test admin can create role
     */
    public function test_admin_can_create_role()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'custom_role',
            'permissions' => [],
        ]);

        $response->assertStatus(201) // JSON response for API
            ->assertJson(['status' => true]);
    }

    /**
     * Test unauthorized access to admin routes
     */
    public function test_non_admin_cannot_access_admin_routes()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);
    }
}

