<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Visit;
use App\Models\Complaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TechnicianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test technician can view assigned visits
     */
    public function test_technician_can_view_assigned_visits()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit(['technician_id' => $technician->id]);

        $response = $this->getJson('/api/tech/visits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'status', 'scheduled_date']
                ]
            ]);
    }

    /**
     * Test technician can accept a visit
     */
    public function test_technician_can_accept_visit()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit([
            'technician_id' => $technician->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/tech/visits/{$visit->id}/accept");

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'accepted',
        ]);
    }

    /**
     * Test technician cannot accept other technician's visit
     */
    public function test_technician_cannot_accept_other_technician_visit()
    {
        $technician1 = $this->createTechnician();
        $technician2 = $this->createTechnician();
        Sanctum::actingAs($technician1);

        $visit = $this->createVisit(['technician_id' => $technician2->id]);

        $response = $this->postJson("/api/tech/visits/{$visit->id}/accept");

        $response->assertStatus(403);
    }

    /**
     * Test technician can start a visit
     */
    public function test_technician_can_start_visit()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit([
            'technician_id' => $technician->id,
            'status' => 'accepted',
        ]);

        $response = $this->postJson("/api/tech/visits/{$visit->id}/start");

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test technician can complete a visit
     */
    public function test_technician_can_complete_visit()
    {
        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit([
            'technician_id' => $technician->id,
            'status' => 'in_progress',
        ]);

        $response = $this->postJson("/api/tech/visits/{$visit->id}/complete", [
            'notes' => 'Visit completed successfully',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test technician can upload visit photo
     */
    public function test_technician_can_upload_visit_photo()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit(['technician_id' => $technician->id]);

        $file = UploadedFile::fake()->image('visit.jpg');

        $response = $this->postJson("/api/tech/visits/{$visit->id}/photos", [
            'photo' => $file,
            'type' => 'after',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'photo_path', 'type']
            ]);
    }

    /**
     * Test unauthorized access to technician routes
     */
    public function test_non_technician_cannot_access_technician_routes()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $visit = $this->createVisit();

        $response = $this->getJson('/api/tech/visits');
        $response->assertStatus(403);
    }
}

