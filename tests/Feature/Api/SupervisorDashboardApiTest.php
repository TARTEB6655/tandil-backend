<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Complaint;
use App\Models\Product;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $client;
    private User $technician;
    private Area $area;
    private Subscription $subscription;
    private Visit $visit;
    private Report $report;
    private Complaint $complaint;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->client = User::factory()->create(['role' => 'client']);
        $this->technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($this->supervisor, 'supervisor');
        $this->assignRoleIfAvailable($this->client, 'client');
        $this->assignRoleIfAvailable($this->technician, 'technician');

        $this->area = Area::factory()->create();
        $this->supervisor->supervisedAreas()->attach($this->area->id);
        $this->area->technicians()->attach($this->technician->id);

        $this->subscription = Subscription::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $this->visit = Visit::factory()->create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'technician_id' => $this->technician->id,
            'status' => 'pending',
            'scheduled_date' => Carbon::today()->toDateString(),
        ]);

        $this->report = Report::factory()->create([
            'visit_id' => $this->visit->id,
            'supervisor_id' => null,
            'status' => 'pending',
        ]);

        $this->complaint = Complaint::factory()->create([
            'visit_id' => $this->visit->id,
            'client_id' => $this->client->id,
            'status' => 'open',
        ]);

        $this->token = $this->supervisor->createToken('test')->plainTextToken;
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
            // Keep tests resilient if role tables are not available in this environment.
        }
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    public function test_supervisor_routes_require_authentication(): void
    {
        $this->getJson('/api/supervisor/dashboard/summary')->assertStatus(401);
    }

    public function test_dashboard_and_team_endpoints_return_success(): void
    {
        $endpoints = [
            '/api/supervisor/dashboard/summary',
            '/api/supervisor/dashboard/kpis',
            '/api/supervisor/dashboard/alerts',
            '/api/supervisor/team',
            '/api/supervisor/team-stats',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint, $this->authHeaders());
            $response->assertStatus(200);
            $response->assertJsonPath('success', true);
            $response->assertJsonStructure(['success', 'data']);
        }
    }

    public function test_assignments_endpoints_support_create_and_update_flows(): void
    {
        $unassignedVisit = Visit::factory()->create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'technician_id' => null,
            'status' => 'pending',
            'scheduled_date' => Carbon::today()->addDay()->toDateString(),
        ]);

        $pending = $this->getJson('/api/supervisor/assignments', $this->authHeaders());
        $pending->assertStatus(200)->assertJsonPath('success', true);
        $ids = collect($pending->json('data.data'))->pluck('id')->all();
        $this->assertContains($unassignedVisit->id, $ids, 'Assignable list should contain the unassigned visit');

        $create = $this->post('/api/supervisor/assignments', [
            'visit_id' => (string) $unassignedVisit->id,
            'technician_id' => (string) $this->technician->id,
            'scheduled_date' => Carbon::today()->addDays(2)->toDateString(),
            'note' => 'Assigned from mobile dashboard',
        ], $this->authHeaders());
        $create->assertStatus(201)->assertJsonPath('success', true);

        $update = $this->post('/api/supervisor/assignments/' . $unassignedVisit->id, [
            'note' => 'Updated from form-data',
        ], $this->authHeaders());
        $update->assertStatus(200)->assertJsonPath('success', true);

        // Reassign is only allowed when job is not in pending_acceptance window (or accept_by has passed)
        $unassignedVisit->refresh();
        $unassignedVisit->update(['accept_by' => Carbon::now()->subMinute()]);
        $reassign = $this->post('/api/supervisor/assignments/' . $unassignedVisit->id . '/reassign', [
            'technician_id' => (string) $this->technician->id,
            'reason' => 'Balance workload',
        ], $this->authHeaders());
        $reassign->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_assignments_pending_returns_message_when_no_zones(): void
    {
        $supervisorNoZones = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($supervisorNoZones, 'supervisor');
        $token = $supervisorNoZones->createToken('test')->plainTextToken;

        $pending = $this->getJson('/api/supervisor/assignments', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $pending->assertStatus(200)->assertJsonPath('success', true);
        $pending->assertJsonPath('data.data', []);
        $pending->assertJsonPath('message', 'No zones assigned to you. Ask admin to assign you to areas (Admin Areas) so you can see and assign visits.');
    }

    public function test_reports_list_includes_technician_submitted_report_when_visit_in_progress(): void
    {
        $this->visit->update(['status' => 'in_progress']);
        $this->report->update(['status' => 'pending']);

        $index = $this->getJson('/api/supervisor/reports', $this->authHeaders());
        $index->assertStatus(200)->assertJsonPath('success', true);
        $reports = $index->json('data');
        $this->assertNotEmpty($reports, 'Supervisor should see report when visit is in_progress');
        $ids = collect($reports)->pluck('id')->all();
        $this->assertContains($this->report->id, $ids);
        $first = collect($reports)->firstWhere('id', $this->report->id);
        $this->assertSame('pending', $first['status'] ?? null);
    }

    public function test_reports_show_returns_field_report_by_id(): void
    {
        $this->visit->update(['status' => 'in_progress']);
        $this->report->update(['status' => 'pending', 'technician_notes' => 'Field notes here']);

        $show = $this->getJson('/api/supervisor/reports/' . $this->report->id, $this->authHeaders());
        $show->assertStatus(200)->assertJsonPath('success', true);
        $show->assertJsonPath('data.id', $this->report->id);
        $show->assertJsonPath('data.visit_id', $this->visit->id);
        $show->assertJsonPath('data.status', 'pending');
        $show->assertJsonPath('data.technician_notes', 'Field notes here');
        $show->assertJsonStructure(['success', 'data' => ['id', 'visit_id', 'status', 'technician_notes', 'before_photos', 'after_photos', 'visit']]);
    }

    public function test_reports_show_returns_404_for_other_supervisors_report(): void
    {
        $otherArea = Area::factory()->create();
        $otherVisit = Visit::factory()->create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $otherArea->id,
            'technician_id' => $this->technician->id,
            'status' => 'in_progress',
        ]);
        $otherReport = Report::factory()->create(['visit_id' => $otherVisit->id, 'status' => 'pending']);

        $show = $this->getJson('/api/supervisor/reports/' . $otherReport->id, $this->authHeaders());
        $show->assertStatus(404)->assertJsonPath('success', false)->assertJsonPath('message', 'Report not found.');
    }

    public function test_reports_accept_and_reject(): void
    {
        $this->visit->update(['status' => 'in_progress']);
        $this->report->update(['status' => 'pending']);

        $accept = $this->postJson('/api/supervisor/reports/' . $this->report->id . '/accept', [], $this->authHeaders());
        $accept->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.status', 'approved');

        $this->report->refresh();
        $this->assertSame('approved', $this->report->status);

        $otherVisit = Visit::factory()->create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'technician_id' => $this->technician->id,
            'status' => 'in_progress',
        ]);
        $pendingReport = Report::factory()->create(['visit_id' => $otherVisit->id, 'status' => 'pending']);
        $reject = $this->postJson('/api/supervisor/reports/' . $pendingReport->id . '/reject', [], $this->authHeaders());
        $reject->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.status', 'rejected');
    }

    public function test_reports_generate_and_download(): void
    {
        $generate = $this->postJson('/api/supervisor/reports/generate', [
            'title' => 'Supervisor Weekly Report',
            'type' => 'operational',
            'format' => 'csv',
        ], $this->authHeaders());
        $generate->assertStatus(201)->assertJsonPath('success', true);

        $generatedId = $generate->json('data.id');
        $this->assertNotNull($generatedId);

        $download = $this->get('/api/supervisor/reports/' . $generatedId . '/download', $this->authHeaders());
        $download->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $download->headers->get('content-type'));
    }

    public function test_profile_endpoints_support_form_data_and_picture_upload(): void
    {
        Storage::fake('public');

        $profile = $this->getJson('/api/supervisor/profile', $this->authHeaders());
        $profile->assertStatus(200)->assertJsonPath('success', true);

        $update = $this->post('/api/supervisor/profile', [
            'name' => 'Supervisor Updated',
            'phone' => '+971500001234',
        ], $this->authHeaders());
        $update->assertStatus(200)->assertJsonPath('success', true);

        $picture = UploadedFile::fake()->image('supervisor.png');
        $upload = $this->post('/api/supervisor/profile', [
            'profile_picture' => $picture,
        ], $this->authHeaders());
        $upload->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNotEmpty($upload->json('data.profile_picture'));

        $password = $this->post('/api/supervisor/profile', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $this->authHeaders());
        $password->assertStatus(200)->assertJsonPath('success', true);

        $preferences = $this->getJson('/api/supervisor/profile/preferences', $this->authHeaders());
        $preferences->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_legacy_supervisor_screen_endpoints_return_success(): void
    {
        $this->getJson('/api/supervisor/visits', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->getJson('/api/supervisor/visits/' . $this->visit->id, $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $recommend = $this->post('/api/supervisor/visits/' . $this->visit->id . '/recommend', [
            'product_ids' => json_encode([$product->id]),
        ], $this->authHeaders());
        $recommend->assertStatus(200)->assertJsonPath('status', true);

        $finalize = $this->post('/api/supervisor/visits/' . $this->visit->id . '/finalize', [
            'notes' => 'Supervisor finalized notes from mobile',
        ], $this->authHeaders());
        $finalize->assertStatus(200)->assertJsonPath('status', true);

        $status = $this->post('/api/supervisor/visits/' . $this->visit->id . '/status', [
            'status' => 'approved',
        ], $this->authHeaders());
        $status->assertStatus(200)->assertJsonPath('status', true);

        $this->getJson('/api/supervisor/areas', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->getJson('/api/supervisor/complaints', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $escalate = $this->post('/api/supervisor/complaints/' . $this->complaint->id . '/escalate', [
            'status' => 'escalated',
            'note' => 'Needs higher escalation',
        ], $this->authHeaders());
        $escalate->assertStatus(200)->assertJsonPath('status', true);
    }

    public function test_inactive_technician_not_in_team_and_assign_fails(): void
    {
        $this->technician->update(['status' => 'inactive']);

        $team = $this->getJson('/api/supervisor/team', $this->authHeaders());
        $team->assertStatus(200)->assertJsonPath('success', true);
        $memberList = $team->json('data') ?? [];
        $this->assertIsArray($memberList);
        $technicianMember = collect($memberList)->firstWhere('id', $this->technician->id);
        $this->assertNotNull($technicianMember, 'Inactive technician must appear in team list so supervisor/AM/admin see status');
        $this->assertSame('inactive', $technicianMember['account_status'] ?? null, 'Inactive member must have account_status inactive');

        $unassignedVisit = Visit::factory()->create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'technician_id' => null,
            'status' => 'pending',
            'scheduled_date' => Carbon::today()->addDay()->toDateString(),
        ]);
        $assign = $this->post('/api/supervisor/assignments', [
            'visit_id' => (string) $unassignedVisit->id,
            'technician_id' => (string) $this->technician->id,
        ], $this->authHeaders());
        $assign->assertStatus(404);
        $assign->assertJsonPath('message', 'Technician not found or inactive. Only active technicians can be assigned.');
    }
}

