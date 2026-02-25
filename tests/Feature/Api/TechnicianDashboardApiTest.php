<?php

namespace Tests\Feature\Api;

use App\Models\TechnicianBreak;
use App\Models\TechnicianVacation;
use App\Models\User;
use App\Models\Visit;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->technician = User::factory()->create(['role' => 'technician']);
        if (method_exists($this->technician, 'assignRole')) {
            $this->technician->assignRole('technician');
        }
        $this->token = $this->technician->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->getJson('/api/technician/dashboard');
        $response->assertStatus(401);
    }

    public function test_dashboard_requires_technician_role(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        if (method_exists($client, 'assignRole')) {
            $client->assignRole('client');
        }
        $token = $client->createToken('test')->plainTextToken;
        $response = $this->getJson('/api/technician/dashboard', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(403);
    }

    public function test_dashboard_returns_name_and_today_tasks(): void
    {
        $response = $this->getJson('/api/technician/dashboard', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'name',
                'email',
                'employee_id',
                'is_online',
                'weekly_kpis' => ['earnings', 'visits_done', 'rating'],
                'today_tasks',
            ],
        ]);
    }

    public function test_profile_get_returns_profile_data(): void
    {
        $response = $this->getJson('/api/technician/profile', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', $this->technician->name);
        $response->assertJsonPath('data.email', $this->technician->email);
    }

    public function test_profile_put_updates_name(): void
    {
        $response = $this->putJson('/api/technician/profile', [
            'name' => 'Updated Name',
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Updated Name');
        $this->technician->refresh();
        $this->assertSame('Updated Name', $this->technician->name);
    }

    public function test_tasks_list_returns_paginated_visits(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::today(),
            'status' => 'pending',
        ]);
        $response = $this->getJson('/api/technician/tasks', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['data', 'current_page']]);
    }

    public function test_task_accept_success(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::today(),
            'status' => 'pending',
        ]);
        $response = $this->postJson("/api/technician/tasks/{$visit->id}/accept", [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'accepted');
        $visit->refresh();
        $this->assertSame('accepted', $visit->status);
    }

    public function test_task_reject_success(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::today(),
            'status' => 'pending',
        ]);
        $response = $this->postJson("/api/technician/tasks/{$visit->id}/reject", ['reason' => 'Not available'], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'rejected');
        $visit->refresh();
        $this->assertSame('rejected', $visit->status);
    }

    public function test_jobs_returns_summary_and_list(): void
    {
        $response = $this->getJson('/api/technician/jobs?period=month', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['summary' => ['total_earnings', 'jobs_completed', 'avg_rating'], 'jobs']]);
    }

    public function test_jobs_excludes_today_and_shows_past_jobs_only(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);

        $todayVisit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::today(),
            'status' => 'in_progress',
        ]);

        $pastVisit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::yesterday(),
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/technician/jobs?period=month&per_page=50', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $ids = collect($response->json('data.jobs.data'))->pluck('id')->all();
        $this->assertContains($pastVisit->id, $ids);
        $this->assertNotContains($todayVisit->id, $ids);
    }

    public function test_accepted_jobs_endpoint_returns_accepted_and_in_progress_only(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        $accepted = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'accepted',
            'scheduled_date' => Carbon::today(),
        ]);
        Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'rejected',
            'scheduled_date' => Carbon::today(),
        ]);

        $response = $this->getJson('/api/technician/jobs/accepted?period=month&per_page=50', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($accepted->id, $ids);
    }

    public function test_rejected_jobs_endpoint_returns_rejected_only(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        $rejected = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'rejected',
            'scheduled_date' => Carbon::today(),
        ]);
        Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'accepted',
            'scheduled_date' => Carbon::today(),
        ]);

        $response = $this->getJson('/api/technician/jobs/rejected?period=month&per_page=50', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($rejected->id, $ids);
        foreach ($response->json('data.data') as $row) {
            $this->assertSame('rejected', $row['status']);
        }
    }

    public function test_jobs_status_counts_endpoint_returns_status_aggregates(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'accepted',
            'scheduled_date' => Carbon::today(),
        ]);
        Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'rejected',
            'scheduled_date' => Carbon::today(),
        ]);

        $response = $this->getJson('/api/technician/jobs/status-counts?period=month', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => ['accepted', 'in_progress', 'rejected', 'completed', 'pending', 'cancelled'],
        ]);
    }

    public function test_payout_summary_returns_stub(): void
    {
        $response = $this->getJson('/api/technician/payout-summary', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.available_balance', 0);
    }

    public function test_availability_get_and_put(): void
    {
        $response = $this->getJson('/api/technician/availability', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.is_online', true);

        $response2 = $this->putJson('/api/technician/availability', [
            'is_online' => false,
            'auto_accept_jobs' => true,
            'working_days' => ['mon', 'tue', 'wed'],
        ], $this->authHeaders());
        $response2->assertStatus(200);
        $response2->assertJsonPath('success', true);
        $this->assertDatabaseHas('technician_availability', [
            'user_id' => $this->technician->id,
            'is_online' => false,
        ]);
    }

    public function test_breaks_crud(): void
    {
        $response = $this->postJson('/api/technician/breaks', [
            'date' => Carbon::today()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'reason' => 'Lunch',
        ], $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('technician_breaks', ['user_id' => $this->technician->id]);

        $response2 = $this->getJson('/api/technician/breaks', $this->authHeaders());
        $response2->assertStatus(200);
        $response2->assertJsonCount(1, 'data');

        $response3 = $this->putJson("/api/technician/breaks/{$id}", ['reason' => 'Updated'], $this->authHeaders());
        $response3->assertStatus(200);

        $response4 = $this->deleteJson("/api/technician/breaks/{$id}", [], $this->authHeaders());
        $response4->assertStatus(200);
        $this->assertDatabaseMissing('technician_breaks', ['id' => $id]);
    }

    public function test_vacations_crud(): void
    {
        $response = $this->postJson('/api/technician/vacations', [
            'start_date' => Carbon::today()->addDays(7)->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'reason' => 'Leave',
        ], $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('technician_vacations', ['user_id' => $this->technician->id]);

        $response2 = $this->getJson('/api/technician/vacations', $this->authHeaders());
        $response2->assertStatus(200);

        $response3 = $this->deleteJson("/api/technician/vacations/{$id}", [], $this->authHeaders());
        $response3->assertStatus(200);
    }

    public function test_schedule_returns_tasks_breaks_vacations(): void
    {
        $response = $this->getJson('/api/technician/schedule?from=' . Carbon::now()->startOfWeek()->toDateString() . '&to=' . Carbon::now()->endOfWeek()->toDateString(), $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['tasks', 'breaks', 'vacations']]);
    }

    public function test_task_show_returns_404_for_other_technician_visit(): void
    {
        $otherTech = User::factory()->create(['role' => 'technician']);
        if (method_exists($otherTech, 'assignRole')) {
            $otherTech->assignRole('technician');
        }
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $otherTech->id,
            'status' => 'pending',
        ]);
        $response = $this->getJson("/api/technician/tasks/{$visit->id}", $this->authHeaders());
        $response->assertStatus(404);
    }

    public function test_task_update_status_to_in_progress(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'accepted',
        ]);
        $response = $this->putJson("/api/technician/tasks/{$visit->id}/status", ['status' => 'in_progress'], $this->authHeaders());
        $response->assertStatus(200);
        $visit->refresh();
        $this->assertSame('in_progress', $visit->status);
    }

    public function test_job_detail_returns_mobile_screen_payload(): void
    {
        $client = User::factory()->create(['role' => 'client', 'phone' => '+971501112233']);
        $sub = Subscription::factory()->create(['client_id' => $client->id, 'plan' => '3_month']);
        $visit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'status' => 'in_progress',
            'notes' => '[DUMMY-SUP-ASSIGN] Mohammed Ali Farm | Tree Watering & Irrigation Check | Al Ain Oasis, Abu Dhabi, UAE | 120 min | AED 289.99 | 5/5',
        ]);

        $response = $this->getJson("/api/technician/jobs/{$visit->id}/detail", $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.job_id', $visit->id);
        $response->assertJsonPath('data.status', 'in_progress');
        $response->assertJsonPath('data.service_information.title', 'Tree Watering & Irrigation Check');
        $response->assertJsonPath('data.customer_information.name', 'Mohammed Ali Farm');
        $response->assertJsonPath('data.service_address.address', 'Al Ain Oasis, Abu Dhabi, UAE');
        $response->assertJsonStructure([
            'data' => [
                'job_number',
                'service_information' => ['title', 'time', 'duration_minutes'],
                'customer_information' => ['name', 'phone', 'email'],
                'before_after_photos' => ['before', 'after', 'other'],
                'actions' => ['can_submit_field_report', 'can_complete_visit', 'can_call_customer'],
            ],
        ]);
    }
}
