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
            '/api/supervisor/dashboard/team-status',
            '/api/supervisor/dashboard/alerts',
            '/api/supervisor/team/statistics',
            '/api/supervisor/team/performance',
            '/api/supervisor/team/attendance',
            '/api/supervisor/team/workload',
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

        $pending = $this->getJson('/api/supervisor/assignments/pending', $this->authHeaders());
        $pending->assertStatus(200)->assertJsonPath('success', true);

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

        $reassign = $this->post('/api/supervisor/assignments/' . $unassignedVisit->id . '/reassign', [
            'technician_id' => (string) $this->technician->id,
            'reason' => 'Balance workload',
        ], $this->authHeaders());
        $reassign->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_reports_endpoints_support_generate_show_and_download(): void
    {
        $index = $this->getJson('/api/supervisor/reports', $this->authHeaders());
        $index->assertStatus(200)->assertJsonPath('success', true);

        $generate = $this->postJson('/api/supervisor/reports/generate', [
            'title' => 'Supervisor Weekly Report',
            'type' => 'operational',
            'format' => 'csv',
        ], $this->authHeaders());
        $generate->assertStatus(201)->assertJsonPath('success', true);

        $reportId = $generate->json('data.id');
        $this->assertNotNull($reportId);

        $show = $this->getJson('/api/supervisor/reports/' . $reportId, $this->authHeaders());
        $show->assertStatus(200)->assertJsonPath('success', true);

        $download = $this->get('/api/supervisor/reports/' . $reportId . '/download', $this->authHeaders());
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
}

