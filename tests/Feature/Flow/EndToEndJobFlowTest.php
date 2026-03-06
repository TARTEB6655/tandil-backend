<?php

namespace Tests\Feature\Flow;

use App\Models\Area;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EndToEndJobFlowTest extends TestCase
{
    use RefreshDatabase;

    private function assignRoleIfAvailable(User $user, string $role): void
    {
        try {
            if (! class_exists(Role::class) || ! method_exists($user, 'assignRole')) {
                return;
            }
            // Create role when tables exist; swallow errors if tables are not present in this environment.
            if (Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }
            $user->assignRole($role);
        } catch (\Throwable $e) {
            // Keep tests resilient if role tables are not available in this environment.
        }
    }

    private function jsonHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    public function test_client_to_supervisor_to_technician_to_client_flow(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($technician, 'technician');

        $area = Area::factory()->create();
        $supervisor->supervisedAreas()->attach($area->id);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        // Client creates visit with area_id but there are no technicians in the zone yet → auto-dispatch escalates to supervisor.
        Sanctum::actingAs($client);
        $createVisit = $this->postJson('/api/visits', [
            'subscription_id' => $subscription->id,
            'area_id' => $area->id,
            'scheduled_date' => Carbon::today()->addDay()->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Farm | Tree Watering Visit | City | 30 min | AED 100.00 | 5/5',
        ], $this->jsonHeaders());
        $createVisit->assertStatus(201)->assertJsonPath('status', true);
        $visitId = (int) $createVisit->json('data.id');
        $this->assertGreaterThan(0, $visitId);

        // Now link technician to the area so supervisor can assign from their team.
        $area->technicians()->attach($technician->id);

        // Supervisor sees it as assignable (because it escalated or is pending in their zones).
        Sanctum::actingAs($supervisor);
        $pending = $this->getJson('/api/supervisor/assignments/pending?per_page=50', $this->jsonHeaders());
        $pending->assertStatus(200)->assertJsonPath('success', true);
        $pendingIds = collect($pending->json('data.data'))->pluck('id')->all();
        $this->assertContains($visitId, $pendingIds);

        // Supervisor assigns (offers) to technician.
        $assign = $this->post('/api/supervisor/assignments', [
            'visit_id' => (string) $visitId,
            'technician_id' => (string) $technician->id,
            'note' => 'Assigned from supervisor',
        ], $this->jsonHeaders());
        $assign->assertStatus(201)->assertJsonPath('success', true);

        // Technician accepts task (accept sets in_progress).
        Sanctum::actingAs($technician);
        $accept = $this->postJson("/api/technician/tasks/{$visitId}/accept", [], $this->jsonHeaders());
        $accept->assertStatus(200)->assertJsonPath('success', true);
        $accept->assertJsonPath('data.status', 'in_progress');

        // Technician submits field report to supervisor.
        $submitReport = $this->post('/api/technician/reports', [
            'supervisor_id' => (string) $supervisor->id,
            'technician_notes' => 'Completed watering. Minor leak repaired.',
        ], $this->jsonHeaders());
        $submitReport->assertStatus(201);
        $submitReport->assertJsonPath('status', true);
        $reportId = (int) $submitReport->json('data.id');
        $this->assertGreaterThan(0, $reportId);

        // Supervisor sees the pending report.
        Sanctum::actingAs($supervisor);
        $reportsPending = $this->getJson('/api/supervisor/reports?status=pending&per_page=20', $this->jsonHeaders());
        $reportsPending->assertStatus(200)->assertJsonPath('success', true);
        $reportIds = collect($reportsPending->json('data'))->pluck('id')->all();
        $this->assertContains($reportId, $reportIds);

        // Supervisor accepts the field report.
        $approve = $this->postJson("/api/supervisor/reports/{$reportId}/accept", [], $this->jsonHeaders());
        $approve->assertStatus(200)->assertJsonPath('success', true);

        // Technician completes visit (allowed only after supervisor accepted report).
        Sanctum::actingAs($technician);
        $complete = $this->putJson("/api/technician/tasks/{$visitId}/status", ['status' => 'completed'], $this->jsonHeaders());
        $complete->assertStatus(200)->assertJsonPath('success', true);

        // Supervisor finalizes and sends report to client.
        Sanctum::actingAs($supervisor);
        $finalize = $this->postJson("/api/supervisor/visits/{$visitId}/finalize", [
            'status' => 'sent_to_client',
            'supervisor_notes' => 'Good job. Follow up next week.',
            'recommendations' => ['Fix irrigation leak', 'Add fertilizer'],
        ], $this->jsonHeaders());
        $finalize->assertStatus(200)->assertJsonPath('status', true);
        $finalize->assertJsonPath('data.status', 'sent_to_client');

        // Client can see their report in /api/reports index.
        Sanctum::actingAs($client);
        $clientReports = $this->getJson('/api/reports', $this->jsonHeaders());
        $clientReports->assertStatus(200)->assertJsonPath('status', true);
        $clientReportIds = collect($clientReports->json('data'))->pluck('id')->all();
        $this->assertContains($reportId, $clientReportIds);

        // And the report status is sent_to_client.
        $report = Report::find($reportId);
        $this->assertSame('sent_to_client', $report?->status);

        // Visit is completed.
        $visit = Visit::find($visitId);
        $this->assertSame('completed', $visit?->status);
        $this->assertSame($technician->id, $visit?->technician_id);
    }
}

