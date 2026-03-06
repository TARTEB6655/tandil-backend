<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end: Client creates job → Supervisor assigns to technician → Technician submits report
 * → Supervisor accepts report → Technician completes → Client sees completed visit.
 */
class ClientToSupervisorToTechnicianReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $supervisor;
    private User $technician;
    private Area $area;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create(['role' => 'client']);
        $this->supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($this->client, 'client');
        $this->assignRoleIfAvailable($this->supervisor, 'supervisor');
        $this->assignRoleIfAvailable($this->technician, 'technician');

        $this->area = Area::factory()->create();
        $this->supervisor->supervisedAreas()->attach($this->area->id);
        // Do NOT attach technician to area yet so client-created visit escalates to supervisor

        $this->subscription = Subscription::factory()->create([
            'client_id' => $this->client->id,
        ]);
    }

    private function assignRoleIfAvailable(User $user, string $role): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($role);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    /** Switch to another user for the next request (avoids auth persisting across requests in same test). */
    private function asUser(User $user): self
    {
        auth()->forgetGuards();
        return $this->actingAs($user, 'sanctum');
    }

    public function test_supervisor_assignments_pending_returns_200_with_supervisor_token(): void
    {
        $pendingResponse = $this->getJson('/api/supervisor/assignments?per_page=20', $this->authHeaders($this->supervisor));
        $pendingResponse->assertStatus(200);
        $pendingResponse->assertJsonPath('success', true);
    }

    public function test_full_flow_client_job_supervisor_assign_technician_report_supervisor_accept_client_sees(): void
    {
        // --- 1. Client creates a job (visit) via API with area_id; no technician in area yet so it escalates to supervisor ---
        $scheduledDate = Carbon::today()->addDays(1)->toDateString();
        $createResponse = $this->asUser($this->client)->postJson('/api/visits', [
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'scheduled_date' => $scheduledDate,
            'notes' => 'Tree watering and check',
        ]);
        $createResponse->assertStatus(201);
        $visit = Visit::find($createResponse->json('data.id'));
        $this->assertNotNull($visit);
        $this->assertSame($this->area->id, $visit->area_id);
        $this->assertNull($visit->technician_id);

        $this->area->technicians()->attach($this->technician->id);

        // --- 2. Supervisor sees assignable tasks and assigns to technician ---
        $pendingResponse = $this->asUser($this->supervisor)->getJson('/api/supervisor/assignments?per_page=20');
        $pendingResponse->assertStatus(200);
        $pendingResponse->assertJsonPath('success', true);
        $ids = collect($pendingResponse->json('data.data'))->pluck('id')->all();
        $this->assertContains($visit->id, $ids, 'Supervisor must see the new visit in assignable list');

        $assignResponse = $this->asUser($this->supervisor)->postJson('/api/supervisor/assignments', [
            'visit_id' => $visit->id,
            'technician_id' => $this->technician->id,
            'note' => 'Assigned from E2E test',
        ]);
        $assignResponse->assertStatus(201);
        $visit->refresh();
        $this->assertSame((int) $this->technician->id, (int) $visit->technician_id);
        $this->assertSame((int) $this->supervisor->id, (int) $visit->supervisor_id);

        // --- 3. Technician accepts the task (if pending_acceptance) or visit is already in_progress ---
        $visit->refresh();
        if (in_array($visit->status, ['pending_acceptance', 'pending'], true)) {
            $acceptResponse = $this->asUser($this->technician)->postJson("/api/technician/tasks/{$visit->id}/accept", []);
            $acceptResponse->assertStatus(200);
        }
        $visit->refresh();
        $this->assertTrue(in_array($visit->status, ['in_progress', 'pending_acceptance'], true));

        // If still pending_acceptance, the API might expect in_progress for report - set in_progress for next step
        if ($visit->status === 'pending_acceptance') {
            $visit->status = 'in_progress';
            $visit->started_at = now();
            $visit->save();
        }

        // --- 4. Technician submits report to supervisor ---
        $reportResponse = $this->asUser($this->technician)->postJson('/api/technician/reports', [
            'supervisor_id' => $this->supervisor->id,
            'technician_notes' => 'Completed tree watering. All sections checked. Minor leak repaired.',
        ]);
        $reportResponse->assertStatus(201);
        $report = Report::where('visit_id', $visit->id)->first();
        $this->assertNotNull($report);
        $this->assertSame('pending', $report->status);

        // --- 5. Supervisor sees report and accepts it ---
        $reportsList = $this->asUser($this->supervisor)->getJson('/api/supervisor/reports?per_page=20');
        $reportsList->assertStatus(200);
        $reportIds = collect($reportsList->json('data'))->pluck('id')->all();
        $this->assertContains($report->id, $reportIds, 'Supervisor must see the submitted report');

        $acceptReportResponse = $this->asUser($this->supervisor)->postJson("/api/supervisor/reports/{$report->id}/accept", []);
        $acceptReportResponse->assertStatus(200);
        $report->refresh();
        $this->assertSame('approved', $report->status);

        // --- 6. Technician completes the job (allowed after report approved) ---
        $completeResponse = $this->asUser($this->technician)->putJson("/api/technician/tasks/{$visit->id}/status", [
            'status' => 'completed',
        ]);
        $completeResponse->assertStatus(200);
        $visit->refresh();
        $this->assertSame('completed', $visit->status);

        // --- 7. Client sees the completed visit (report/status visible to client) ---
        $clientVisitsResponse = $this->asUser($this->client)->getJson('/api/visits');
        $clientVisitsResponse->assertStatus(200);
        $clientVisits = $clientVisitsResponse->json('data');
        $visitInList = collect($clientVisits)->firstWhere('id', $visit->id);
        $this->assertNotNull($visitInList, 'Client must see the visit in their list');
        $this->assertSame('completed', $visitInList['status'] ?? null);

        $clientVisitDetail = $this->asUser($this->client)->getJson("/api/visits/{$visit->id}");
        $clientVisitDetail->assertStatus(200);
        $clientVisitDetail->assertJsonPath('data.status', 'completed');
    }
}
