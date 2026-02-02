<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Visit;
use App\Models\Area;
use App\Models\Product;
use App\Models\Complaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SupervisorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test supervisor can list visits in supervised areas
     */
    public function test_supervisor_can_list_visits()
    {
        $supervisor = $this->createSupervisor();
        $area = $this->createArea();
        
        // Attach supervisor to area
        $area->supervisors()->attach($supervisor->id);
        
        Sanctum::actingAs($supervisor);

        $visit = $this->createVisit(['area_id' => $area->id]);

        $response = $this->getJson('/api/supervisor/visits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test supervisor can review a visit
     */
    public function test_supervisor_can_review_visit()
    {
        $supervisor = $this->createSupervisor();
        $area = $this->createArea();
        $area->supervisors()->attach($supervisor->id);
        
        Sanctum::actingAs($supervisor);

        $visit = $this->createVisit(['area_id' => $area->id]);

        $response = $this->getJson("/api/supervisor/visits/{$visit->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'status', 'subscription']
            ]);
    }

    /**
     * Test supervisor can recommend products
     */
    public function test_supervisor_can_recommend_products()
    {
        $supervisor = $this->createSupervisor();
        $area = $this->createArea();
        $area->supervisors()->attach($supervisor->id);
        
        Sanctum::actingAs($supervisor);

        $visit = $this->createVisit(['area_id' => $area->id]);
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $response = $this->postJson("/api/supervisor/visits/{$visit->id}/recommend", [
            'product_ids' => [$product1->id, $product2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    /**
     * Test supervisor can finalize report
     */
    public function test_supervisor_can_finalize_report()
    {
        $supervisor = $this->createSupervisor();
        $area = $this->createArea();
        $area->supervisors()->attach($supervisor->id);
        
        Sanctum::actingAs($supervisor);

        $visit = $this->createVisit(['area_id' => $area->id]);

        $response = $this->postJson("/api/supervisor/visits/{$visit->id}/finalize", [
            'notes' => 'Report finalized',
            'status' => 'finalized',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    /**
     * Test supervisor can update visit status
     */
    public function test_supervisor_can_update_visit_status()
    {
        $supervisor = $this->createSupervisor();
        $area = $this->createArea();
        $area->supervisors()->attach($supervisor->id);
        
        Sanctum::actingAs($supervisor);

        $visit = $this->createVisit(['area_id' => $area->id]);

        $response = $this->postJson("/api/supervisor/visits/{$visit->id}/status", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    /**
     * Test supervisor can list complaints
     */
    public function test_supervisor_can_list_complaints()
    {
        $supervisor = $this->createSupervisor();
        $area = $this->createArea();
        $area->supervisors()->attach($supervisor->id);
        
        Sanctum::actingAs($supervisor);

        $visit = $this->createVisit(['area_id' => $area->id]);
        $complaint = $this->createComplaint(['visit_id' => $visit->id]);

        $response = $this->getJson('/api/supervisor/complaints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test supervisor can escalate complaint
     */
    public function test_supervisor_can_escalate_complaint()
    {
        $supervisor = $this->createSupervisor();
        $area = $this->createArea();
        $area->supervisors()->attach($supervisor->id);
        
        Sanctum::actingAs($supervisor);

        $visit = $this->createVisit(['area_id' => $area->id]);
        $complaint = $this->createComplaint(['visit_id' => $visit->id]);

        $response = $this->postJson("/api/supervisor/complaints/{$complaint->id}/escalate", [
            'status' => 'escalated',
            'note' => 'Escalated to management',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    /**
     * Test unauthorized access to supervisor routes
     */
    public function test_non_supervisor_cannot_access_supervisor_routes()
    {
        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/supervisor/visits');
        $response->assertStatus(403);
    }
}




