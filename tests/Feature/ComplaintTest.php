<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Visit;
use App\Models\Complaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test client can create complaint
     */
    public function test_client_can_create_complaint()
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $subscription = $this->createSubscription(['client_id' => $client->id]);
        $visit = $this->createVisit(['subscription_id' => $subscription->id]);

        $response = $this->postJson('/api/auth/complaints', [
            'visit_id' => $visit->id,
            'notes' => 'Test complaint',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'visit_id', 'client_id', 'notes']
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * Test client can view own complaints
     */
    public function test_client_can_view_own_complaints()
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $subscription = $this->createSubscription(['client_id' => $client->id]);
        $visit = $this->createVisit(['subscription_id' => $subscription->id]);

        $complaint = $this->createComplaint([
            'visit_id' => $visit->id,
            'client_id' => $client->id,
        ]);

        $response = $this->getJson('/api/auth/complaints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * Test admin can view all complaints
     */
    public function test_admin_can_view_all_complaints()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->createComplaint();
        $this->createComplaint();

        $response = $this->getJson('/api/auth/complaints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * Test admin can update complaint
     */
    public function test_admin_can_update_complaint()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $complaint = $this->createComplaint();

        $response = $this->putJson("/api/auth/complaints/{$complaint->id}", [
            'status' => 'resolved',
            'notes' => 'Updated notes',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'resolved',
        ]);
    }

    /**
     * Test admin can delete complaint
     */
    public function test_admin_can_delete_complaint()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $complaint = $this->createComplaint();

        $response = $this->deleteJson("/api/auth/complaints/{$complaint->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('complaints', [
            'id' => $complaint->id,
        ]);
    }

    /**
     * Test complaint creation requires authentication
     */
    public function test_complaint_creation_requires_authentication()
    {
        $response = $this->postJson('/api/auth/complaints', []);

        $response->assertStatus(401);
    }
}

