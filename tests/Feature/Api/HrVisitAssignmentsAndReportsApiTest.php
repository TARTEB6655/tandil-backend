<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrVisitAssignmentsAndReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminHr;

    protected function setUp(): void
    {
        parent::setUp();
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
            'type' => 'hr_technician_monthly',
            'title' => 'Test monthly',
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
    }
}
