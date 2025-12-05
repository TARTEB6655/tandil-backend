<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test technician can list visits
     */
    public function test_technician_can_list_visits()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit(['technician_id' => $technician->id]);

        $response = $this->getJson('/api/visits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test client can view own visits
     */
    public function test_client_can_view_own_visits()
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $subscription = $this->createSubscription(['client_id' => $client->id]);
        $visit = $this->createVisit(['subscription_id' => $subscription->id]);

        $response = $this->getJson('/api/visits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test technician can view visit details
     */
    public function test_technician_can_view_visit_details()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit(['technician_id' => $technician->id]);

        $response = $this->getJson("/api/visits/{$visit->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'status', 'subscription']
            ]);
    }

    /**
     * Test technician can upload photo
     */
    public function test_technician_can_upload_photo()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit(['technician_id' => $technician->id]);

        $file = UploadedFile::fake()->image('visit.jpg');

        $response = $this->postJson("/api/visits/{$visit->id}/upload-photo", [
            'photo' => $file,
            'type' => 'before',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test unauthorized access to visits
     */
    public function test_unauthorized_user_cannot_access_visits()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        // Customer can view their own visits, but let's test with another customer's visit
        $otherCustomer = $this->createCustomer();
        $subscription = $this->createSubscription(['client_id' => $otherCustomer->id]);
        $visit = $this->createVisit(['subscription_id' => $subscription->id]);

        $response = $this->getJson("/api/visits/{$visit->id}");

        // Should be forbidden if not their visit
        $response->assertStatus(403);
    }
}

