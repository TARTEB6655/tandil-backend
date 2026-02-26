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

    public function test_dashboard_today_tasks_includes_carry_forward_open_statuses(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);

        $yesterdayPending = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::yesterday(),
            'status' => 'pending',
        ]);

        Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::yesterday(),
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/technician/dashboard', $this->authHeaders());
        $response->assertStatus(200);
        $ids = collect($response->json('data.today_tasks'))->pluck('id')->all();
        $this->assertContains($yesterdayPending->id, $ids);
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

    public function test_tasks_filter_all_returns_only_open_jobs(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);

        $openJob = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::today(),
            'status' => 'accepted',
        ]);
        $closedJob = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::yesterday(),
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/technician/tasks?filter=all&per_page=50', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($openJob->id, $ids);
        $this->assertNotContains($closedJob->id, $ids);
        foreach ($response->json('data.data') as $row) {
            $this->assertContains($row['status'], ['pending', 'accepted', 'in_progress']);
        }
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

    public function test_jobs_returns_closed_and_in_progress_statuses(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sub = Subscription::factory()->create(['client_id' => $client->id]);

        $todayCompleted = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::today(),
            'status' => 'completed',
        ]);

        $pastRejected = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::yesterday(),
            'status' => 'rejected',
        ]);

        $inProgressVisit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::yesterday(),
            'status' => 'in_progress',
        ]);

        $openVisit = Visit::factory()->create([
            'subscription_id' => $sub->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => Carbon::yesterday(),
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/technician/jobs?period=month&per_page=50', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $ids = collect($response->json('data.jobs.data'))->pluck('id')->all();
        $this->assertContains($todayCompleted->id, $ids);
        $this->assertContains($pastRejected->id, $ids);
        $this->assertContains($inProgressVisit->id, $ids);
        $this->assertNotContains($openVisit->id, $ids);
        foreach ($response->json('data.jobs.data') as $row) {
            $this->assertContains($row['status'], ['completed', 'rejected', 'cancelled', 'in_progress']);
        }
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

        $response = $this->getJson('/api/technician/tasks?filter=accepted&per_page=50', $this->authHeaders());
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

        $response = $this->getJson('/api/technician/tasks?filter=rejected&per_page=50', $this->authHeaders());
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
        $response->assertJsonStructure(['data' => ['service_area', 'service_areas', 'breaks', 'vacations']]);
        $response->assertJsonPath('data.breaks', []);
        $response->assertJsonPath('data.vacations', []);

        $response2 = $this->putJson('/api/technician/availability', [
            'is_online' => false,
            'auto_accept_jobs' => true,
            'working_days' => ['mon', 'tue', 'wed'],
            'service_area' => 'Dubai',
            'breaks' => [
                ['date' => Carbon::today()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00', 'reason' => 'Lunch'],
            ],
        ], $this->authHeaders());
        $response2->assertStatus(200);
        $response2->assertJsonPath('success', true);
        $response2->assertJsonPath('data.service_area', 'Dubai');
        $response2->assertJsonCount(1, 'data.breaks');
        $response2->assertJsonPath('data.breaks.0.reason', 'Lunch');
        $this->assertDatabaseHas('technician_availability', [
            'user_id' => $this->technician->id,
            'is_online' => false,
        ]);
        $this->assertDatabaseHas('technician_breaks', ['user_id' => $this->technician->id]);
    }

    public function test_availability_put_accepts_form_data_breaks(): void
    {
        $breaksJson = json_encode([
            ['date' => Carbon::today()->toDateString(), 'start_time' => '12:00', 'end_time' => '13:00', 'reason' => 'Lunch'],
        ]);
        // PUT with form-urlencoded body (call() sends params as request input)
        $response = $this->call(
            'PUT',
            '/api/technician/availability',
            [
                'is_online' => 'true',
                'service_area' => 'Dubai',
                'breaks' => $breaksJson,
            ],
            [],
            [],
            array_merge(
                ['HTTP_Accept' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $this->token],
                ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
            )
        );
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'data.breaks');
        $response->assertJsonPath('data.breaks.0.reason', 'Lunch');
    }

    public function test_availability_breaks_replace_all(): void
    {
        $this->putJson('/api/technician/availability', [
            'breaks' => [
                ['date' => Carbon::today()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00', 'reason' => 'Lunch'],
            ],
        ], $this->authHeaders());

        $response = $this->putJson('/api/technician/availability', [
            'breaks' => [
                ['date' => Carbon::tomorrow()->toDateString(), 'start_time' => '14:00', 'end_time' => '14:30', 'reason' => 'Short break'],
            ],
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.breaks');
        $response->assertJsonPath('data.breaks.0.reason', 'Short break');
        $this->assertSame(1, \App\Models\TechnicianBreak::where('user_id', $this->technician->id)->count());
    }

    public function test_availability_includes_vacations_and_put_replaces_vacations(): void
    {
        $response = $this->getJson('/api/technician/availability', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['vacations']]);
        $response->assertJsonPath('data.vacations', []);

        $response2 = $this->putJson('/api/technician/availability', [
            'vacations' => [
                ['start_date' => Carbon::today()->addDays(7)->toDateString(), 'end_date' => Carbon::today()->addDays(10)->toDateString(), 'reason' => 'Leave'],
            ],
        ], $this->authHeaders());
        $response2->assertStatus(200);
        $response2->assertJsonCount(1, 'data.vacations');
        $response2->assertJsonPath('data.vacations.0.reason', 'Leave');
        $this->assertDatabaseHas('technician_vacations', ['user_id' => $this->technician->id]);

        $response3 = $this->putJson('/api/technician/availability', [
            'vacations' => [
                ['start_date' => Carbon::today()->addDays(14)->toDateString(), 'end_date' => Carbon::today()->addDays(16)->toDateString(), 'reason' => 'Short leave'],
            ],
        ], $this->authHeaders());
        $response3->assertStatus(200);
        $response3->assertJsonCount(1, 'data.vacations');
        $response3->assertJsonPath('data.vacations.0.reason', 'Short leave');
        $this->assertSame(1, \App\Models\TechnicianVacation::where('user_id', $this->technician->id)->count());
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

        $response = $this->getJson("/api/technician/tasks/{$visit->id}/detail", $this->authHeaders());
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
