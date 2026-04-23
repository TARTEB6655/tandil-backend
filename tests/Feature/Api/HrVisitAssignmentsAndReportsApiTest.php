<?php

namespace Tests\Feature\Api;

use App\Models\AdminReport;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrVisitAssignmentsAndReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminHr;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->adminHr = User::factory()->create(['role' => 'admin', 'name' => 'HR Admin']);
        $this->assignRoleIfAvailable($this->adminHr, 'admin');
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
            //
        }
    }

    public function test_hr_visit_assignments_list_and_assign_screen(): void
    {
        Visit::factory()->create([
            'technician_id' => null,
            'status' => 'pending',
            'scheduled_date' => Carbon::today()->toDateString(),
        ]);

        $list = $this->actingAs($this->adminHr, 'sanctum')
            ->getJson('/api/hr/visit-assignments?per_page=5');
        $list->assertStatus(200)->assertJsonPath('success', true);

        $screen = $this->actingAs($this->adminHr, 'sanctum')
            ->getJson('/api/hr/visit-assignments/assign-screen?per_page=5');
        $screen->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['team_members', 'available_tasks'], 'meta']);
    }

    public function test_hr_visit_assignments_offer_to_technician_smoke(): void
    {
        $tech = User::factory()->create(['role' => 'technician', 'status' => 'active']);
        $this->assignRoleIfAvailable($tech, 'technician');

        $visit = Visit::factory()->create([
            'technician_id' => null,
            'status' => 'pending',
            'scheduled_date' => Carbon::today()->toDateString(),
            'accept_by' => null,
        ]);

        $res = $this->actingAs($this->adminHr, 'sanctum')
            ->postJson('/api/hr/visit-assignments/' . $visit->id, [
                'technician_id' => $tech->id,
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'status'], 'accept_by']);
    }

    public function test_hr_visit_assignments_summary_returns_correct_counts(): void
    {
        $tech = User::factory()->create(['role' => 'technician', 'status' => 'active']);
        $this->assignRoleIfAvailable($tech, 'technician');

        Visit::factory()->create([
            'technician_id' => null,
            'status' => 'pending',
            'scheduled_date' => Carbon::today()->toDateString(),
        ]);
        Visit::factory()->create([
            'technician_id' => null,
            'status' => 'pending_acceptance',
            'scheduled_date' => Carbon::today()->toDateString(),
        ]);
        Visit::factory()->create([
            'technician_id' => $tech->id,
            'status' => 'pending_acceptance',
            'scheduled_date' => Carbon::today()->toDateString(),
        ]);

        $res = $this->actingAs($this->adminHr, 'sanctum')
            ->getJson('/api/hr/visit-assignments/summary?scope=all');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_jobs', 3)
            ->assertJsonPath('data.unassigned', 2)
            ->assertJsonPath('data.pending_acceptance', 2);
    }

    public function test_hr_technician_monthly_preview_requires_technician_role(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');

        $res = $this->actingAs($this->adminHr, 'sanctum')->getJson('/api/hr/reports/technician-monthly?' . http_build_query([
            'technician_id' => $client->id,
            'year' => now()->year,
            'month' => now()->month,
        ]));
        $res->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_hr_technician_monthly_preview_ok_for_technician(): void
    {
        $tech = User::factory()->create(['role' => 'technician', 'status' => 'active']);
        $this->assignRoleIfAvailable($tech, 'technician');

        $res = $this->actingAs($this->adminHr, 'sanctum')->getJson('/api/hr/reports/technician-monthly?' . http_build_query([
            'technician_id' => $tech->id,
            'year' => now()->year,
            'month' => now()->month,
        ]));
        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.technician.id', $tech->id);
    }

    public function test_hr_reports_generate_creates_pending_admin_report(): void
    {
        $tech = User::factory()->create(['role' => 'technician', 'status' => 'active']);
        $this->assignRoleIfAvailable($tech, 'technician');

        $res = $this->actingAs($this->adminHr, 'sanctum')->postJson('/api/hr/reports/generate', [
            'parameters' => [
                'technician_id' => $tech->id,
                'year' => now()->year,
                'month' => now()->month,
                'format' => 'pdf',
            ],
        ]);
        $res->assertStatus(201)->assertJsonPath('success', true);
        $reportId = $res->json('data.id');
        $this->assertNotNull($reportId);
        $this->assertDatabaseHas('admin_reports', [
            'id' => $reportId,
            'type' => 'hr_technician_monthly',
            'created_by' => $this->adminHr->id,
        ]);
        $title = $res->json('data.title');
        $this->assertIsString($title);
        $this->assertNotSame('', trim($title));
        $this->assertNotNull($res->json('data.file_url'));
    }

    public function test_hr_reports_list_generated_smoke(): void
    {
        AdminReport::create([
            'title' => 'My HR Report',
            'type' => 'hr_technician_monthly',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $this->adminHr->id,
        ]);

        $res = $this->actingAs($this->adminHr, 'sanctum')
            ->getJson('/api/hr/reports?per_page=10');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_hr_reports_download_smoke_for_generated_file(): void
    {
        $report = AdminReport::create([
            'title' => 'Ready File',
            'type' => 'hr_technician_monthly',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => 'admin_reports/777/hr-api-report.pdf',
            'parameters' => [],
            'created_by' => $this->adminHr->id,
        ]);
        Storage::disk('local')->put($report->file_path, '%PDF-1.4 fake hr api pdf');

        $res = $this->actingAs($this->adminHr, 'sanctum')
            ->get('/api/hr/reports/' . $report->id . '/download');

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/pdf');
    }

    public function test_hr_reports_public_download_smoke_without_auth(): void
    {
        $report = AdminReport::create([
            'title' => 'Public Ready File',
            'type' => 'hr_technician_monthly',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => 'admin_reports/778/hr-api-public-report.pdf',
            'parameters' => [],
            'created_by' => $this->adminHr->id,
        ]);
        Storage::disk('local')->put($report->file_path, '%PDF-1.4 fake hr public api pdf');

        $res = $this->get('/api/hr/reports/' . $report->id . '/download-public');

        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/pdf');
    }

    public function test_hr_reports_delete_removes_owned_report(): void
    {
        $report = \App\Models\AdminReport::create([
            'title' => 'Delete me',
            'type' => 'hr_technician_monthly',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $this->adminHr->id,
        ]);

        $res = $this->actingAs($this->adminHr, 'sanctum')
            ->deleteJson('/api/hr/reports/' . $report->id);

        $res->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('admin_reports', ['id' => $report->id]);
    }
}
