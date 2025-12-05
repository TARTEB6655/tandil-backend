<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin has access to all routes
     */
    public function test_admin_has_full_access()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        // Test admin can access admin routes
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(200);

        // Test admin can access technician routes (if needed)
        // Test admin can access supervisor routes (if needed)
    }

    /**
     * Test technician can only access technician routes
     */
    public function test_technician_can_only_access_technician_routes()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        // Can access technician routes
        $response = $this->getJson('/api/auth/tech/visits');
        $response->assertStatus(200);

        // Cannot access admin routes
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);
    }

    /**
     * Test supervisor can only access supervisor routes
     */
    public function test_supervisor_can_only_access_supervisor_routes()
    {
        $supervisor = $this->createSupervisor();
        Sanctum::actingAs($supervisor);

        // Can access supervisor routes
        $response = $this->getJson('/api/auth/supervisor/visits');
        $response->assertStatus(200);

        // Cannot access admin routes
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);
    }

    /**
     * Test client can access client routes
     */
    public function test_client_can_access_client_routes()
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        // Can access subscriptions
        $response = $this->getJson('/api/subscriptions');
        $response->assertStatus(200);

        // Cannot access admin routes
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);
    }
}

