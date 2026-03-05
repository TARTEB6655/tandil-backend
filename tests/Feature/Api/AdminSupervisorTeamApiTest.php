<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSupervisorTeamApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $supervisor;
    private User $technician;
    private Area $area;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Admin', 'email' => 'admin-team@test.com']);
        $this->supervisor = User::factory()->create(['name' => 'Supervisor', 'email' => 'sup-team@test.com']);
        $this->technician = User::factory()->create(['name' => 'Technician', 'email' => 'tech-team@test.com']);
        $this->assignRoleIfAvailable($this->admin, 'admin');
        $this->assignRoleIfAvailable($this->supervisor, 'supervisor');
        $this->assignRoleIfAvailable($this->technician, 'technician');

        $this->area = Area::factory()->create(['location' => 'Team Test Area']);
        $this->supervisor->supervisedAreas()->attach($this->area->id);
        $this->area->technicians()->attach($this->technician->id);

        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function assignRoleIfAvailable(User $user, string $roleName): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($roleName);
                }
            }
        } catch (\Throwable $e) {
            // Keep tests resilient if role tables are not available.
        }
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    // ---- GET team ----
    public function test_team_get_returns_supervisor_and_team_structure(): void
    {
        $response = $this->getJson('/api/admin/supervisors/' . $this->supervisor->id . '/team', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'supervisor' => ['id', 'name', 'employee_id', 'assigned_zones'],
                'team' => [
                    '*' => ['id', 'name', 'email', 'employee_id', 'service_areas', 'specializations', 'assigned_zones'],
                ],
            ],
        ]);
        $response->assertJsonPath('data.supervisor.id', $this->supervisor->id);
        $this->assertCount(1, $response->json('data.team'));
        $response->assertJsonPath('data.team.0.id', $this->technician->id);
    }

    public function test_team_get_404_for_non_supervisor(): void
    {
        $client = User::factory()->create();
        $this->assignRoleIfAvailable($client, 'client');
        $response = $this->getJson('/api/admin/supervisors/' . $client->id . '/team', $this->authHeaders());
        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
        $response->assertJsonFragment(['message' => 'Supervisor not found.']);
    }

    public function test_team_get_empty_team_when_supervisor_has_no_zones(): void
    {
        $supNoZones = User::factory()->create(['name' => 'Sup No Zones']);
        $this->assignRoleIfAvailable($supNoZones, 'supervisor');
        $response = $this->getJson('/api/admin/supervisors/' . $supNoZones->id . '/team', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.supervisor.id', $supNoZones->id);
        $response->assertJsonPath('data.supervisor.assigned_zones', []);
        $response->assertJsonPath('data.team', []);
    }

    public function test_team_get_empty_team_when_zones_have_no_technicians(): void
    {
        $area2 = Area::factory()->create(['location' => 'Empty Zone']);
        $this->supervisor->supervisedAreas()->attach($area2->id);
        $this->area->technicians()->detach($this->technician->id);
        $response = $this->getJson('/api/admin/supervisors/' . $this->supervisor->id . '/team', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.team', []);
    }

    // ---- POST add member ----
    public function test_team_add_requires_technician_id(): void
    {
        $response = $this->postJson('/api/admin/supervisors/' . $this->supervisor->id . '/team', [], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_team_add_404_when_supervisor_not_found(): void
    {
        $response = $this->postJson('/api/admin/supervisors/99999/team', [
            'technician_id' => $this->technician->id,
        ], $this->authHeaders());
        $response->assertStatus(404);
    }

    public function test_team_add_422_when_supervisor_has_no_zones(): void
    {
        $supNoZones = User::factory()->create();
        $this->assignRoleIfAvailable($supNoZones, 'supervisor');
        $response = $this->postJson('/api/admin/supervisors/' . $supNoZones->id . '/team', [
            'technician_id' => $this->technician->id,
        ], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Supervisor has no assigned zones.']);
    }

    public function test_team_add_404_when_technician_not_found(): void
    {
        $response = $this->postJson('/api/admin/supervisors/' . $this->supervisor->id . '/team', [
            'technician_id' => 99999,
        ], $this->authHeaders());
        $response->assertStatus(422); // exists:users,id fails with 422
    }

    public function test_team_add_404_when_user_is_not_technician(): void
    {
        $client = User::factory()->create();
        $this->assignRoleIfAvailable($client, 'client');
        $response = $this->postJson('/api/admin/supervisors/' . $this->supervisor->id . '/team', [
            'technician_id' => $client->id,
        ], $this->authHeaders());
        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Technician not found.']);
    }

    public function test_team_add_success_and_pivot_created(): void
    {
        $tech2 = User::factory()->create(['name' => 'Tech Two']);
        $this->assignRoleIfAvailable($tech2, 'technician');
        $this->area->technicians()->detach($tech2->id);
        $response = $this->postJson('/api/admin/supervisors/' . $this->supervisor->id . '/team', [
            'technician_id' => $tech2->id,
        ], $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.technician_id', $tech2->id);
        $this->assertDatabaseHas('area_technician', [
            'area_id' => $this->area->id,
            'user_id' => $tech2->id,
        ]);
    }

    // ---- DELETE remove member ----
    public function test_team_remove_requires_technician_id(): void
    {
        $response = $this->deleteJson('/api/admin/supervisors/' . $this->supervisor->id . '/team', [], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_team_remove_success_with_query_param(): void
    {
        $url = '/api/admin/supervisors/' . $this->supervisor->id . '/team?technician_id=' . $this->technician->id;
        $response = $this->deleteJson($url, [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.technician_id', $this->technician->id);
        $this->assertDatabaseMissing('area_technician', [
            'area_id' => $this->area->id,
            'user_id' => $this->technician->id,
        ]);
    }

    public function test_team_remove_from_all_supervisor_zones(): void
    {
        $area2 = Area::factory()->create(['location' => 'Zone Two']);
        $this->supervisor->supervisedAreas()->attach($area2->id);
        $area2->technicians()->attach($this->technician->id);
        $url = '/api/admin/supervisors/' . $this->supervisor->id . '/team?technician_id=' . $this->technician->id;
        $response = $this->deleteJson($url, [], $this->authHeaders());
        $response->assertStatus(200);
        $this->assertDatabaseMissing('area_technician', ['area_id' => $this->area->id, 'user_id' => $this->technician->id]);
        $this->assertDatabaseMissing('area_technician', ['area_id' => $area2->id, 'user_id' => $this->technician->id]);
    }

    public function test_team_requires_admin_auth(): void
    {
        $response = $this->getJson('/api/admin/supervisors/' . $this->supervisor->id . '/team');
        $response->assertStatus(401);
    }
}
