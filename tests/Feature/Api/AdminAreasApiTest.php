<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAreasApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $supervisor;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@test.com']);
        $this->supervisor = User::factory()->create(['name' => 'Supervisor User', 'email' => 'supervisor@test.com']);
        $this->assignRoleIfAvailable($this->admin, 'admin');
        $this->assignRoleIfAvailable($this->supervisor, 'supervisor');
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

    public function test_areas_list_returns_success_and_structure(): void
    {
        Area::factory()->count(2)->create(['location' => 'Test Location']);
        $response = $this->getJson('/api/admin/areas?per_page=10', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'location', 'supervisor_id'],
            ],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_areas_list_with_all_param_returns_all_without_pagination(): void
    {
        Area::factory()->count(3)->create(['location' => 'Loc']);
        $response = $this->getJson('/api/admin/areas?all=1', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['success', 'message', 'data', 'total']);
        $this->assertCount(3, $response->json('data'));
        $this->assertArrayNotHasKey('pagination', $response->json());
    }

    public function test_areas_list_search_filters_by_location(): void
    {
        Area::factory()->create(['location' => 'Abu Dhabi North']);
        Area::factory()->create(['location' => 'Dubai South']);
        $response = $this->getJson('/api/admin/areas?per_page=10&search=Abu', $this->authHeaders());
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Abu', $data[0]['location']);
    }

    public function test_operational_areas_endpoint_returns_summary_and_paginated_areas_without_map_pins(): void
    {
        $active = Area::factory()->create([
            'name' => 'Abu Dhabi City',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
            'latitude' => 24.4539,
            'longitude' => 54.3773,
        ]);
        $inactive = Area::factory()->create([
            'name' => 'Dubai City',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => false,
            'latitude' => 25.2048,
            'longitude' => 55.2708,
        ]);
        $active->supervisors()->attach($this->supervisor->id);

        $response = $this->getJson('/api/admin/operational-areas?per_page=10&country=UAE', $this->authHeaders());
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.summary.total_zones', 2);
        $response->assertJsonPath('data.summary.operational_zones', 1);
        $response->assertJsonPath('data.summary.pinned_on_map', 1);
        $response->assertJsonPath('pagination.total', 2);
        $response->assertJsonStructure([
            'data' => [
                'summary' => ['total_zones', 'operational_zones', 'pinned_on_map'],
                'areas' => [['id', 'name', 'location', 'country', 'is_active', 'priority', 'supervisors']],
            ],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertArrayNotHasKey('map_pins', $response->json('data'));

        $areaIds = collect($response->json('data.areas'))->pluck('id')->all();
        $this->assertContains($active->id, $areaIds);
        $this->assertContains($inactive->id, $areaIds);
    }

    public function test_operational_area_toggle_endpoint_switches_active_state(): void
    {
        $area = Area::factory()->create(['is_active' => false, 'country' => 'UAE']);

        $response = $this->postJson('/api/admin/operational-areas/' . $area->id . '/toggle-active', [], $this->authHeaders());
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $area->id);
        $response->assertJsonPath('data.is_active', true);
    }

    public function test_area_create_requires_location_and_supervisor_id(): void
    {
        $response = $this->post('/api/admin/areas', [], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        $response2 = $this->post('/api/admin/areas', ['location' => 'Only Location'], $this->authHeaders());
        $response2->assertStatus(422);
    }

    public function test_area_create_rejects_non_supervisor_user(): void
    {
        $client = User::factory()->create();
        $this->assignRoleIfAvailable($client, 'client');
        $response = $this->post('/api/admin/areas', [
            'location' => 'Test Area',
            'supervisor_id' => $client->id,
        ], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonFragment(['message' => 'The selected user is not a supervisor. supervisor_id must be a user with the supervisor role.']);
    }

    public function test_area_create_success_and_returns_id_location_supervisor_id(): void
    {
        $response = $this->post('/api/admin/areas', [
            'location' => 'Abu Dhabi North',
            'supervisor_id' => $this->supervisor->id,
        ], $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.location', 'Abu Dhabi North');
        $response->assertJsonPath('data.supervisor_id', $this->supervisor->id);
        $response->assertJsonStructure(['data' => ['id', 'location', 'supervisor_id']]);
        $this->assertDatabaseHas('areas', ['location' => 'Abu Dhabi North']);
        $this->assertDatabaseHas('area_supervisor', [
            'area_id' => $response->json('data.id'),
            'user_id' => $this->supervisor->id,
        ]);
    }

    public function test_area_show_returns_id_location_supervisor_id(): void
    {
        $area = Area::factory()->create(['location' => 'Show Test']);
        $area->supervisors()->attach($this->supervisor->id);
        $response = $this->getJson('/api/admin/areas/' . $area->id, $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $area->id);
        $response->assertJsonPath('data.location', 'Show Test');
        $response->assertJsonPath('data.supervisor_id', $this->supervisor->id);
    }

    public function test_area_show_404_for_missing_id(): void
    {
        $response = $this->getJson('/api/admin/areas/99999', $this->authHeaders());
        $response->assertStatus(404);
    }

    public function test_area_update_success(): void
    {
        $area = Area::factory()->create(['location' => 'Original']);
        $area->supervisors()->attach($this->supervisor->id);
        $response = $this->put('/api/admin/areas/' . $area->id, [
            'location' => 'Updated Location',
            'supervisor_id' => $this->supervisor->id,
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.location', 'Updated Location');
        $response->assertJsonPath('data.supervisor_id', $this->supervisor->id);
        $this->assertDatabaseHas('areas', ['id' => $area->id, 'location' => 'Updated Location']);
    }

    public function test_area_update_rejects_non_supervisor(): void
    {
        $area = Area::factory()->create(['location' => 'Area']);
        $client = User::factory()->create();
        $this->assignRoleIfAvailable($client, 'client');
        $response = $this->put('/api/admin/areas/' . $area->id, [
            'location' => 'Updated',
            'supervisor_id' => $client->id,
        ], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'The selected user is not a supervisor. supervisor_id must be a user with the supervisor role.']);
    }

    public function test_area_delete_success(): void
    {
        $area = Area::factory()->create(['location' => 'To Delete']);
        $area->supervisors()->attach($this->supervisor->id);
        $id = $area->id;
        $response = $this->deleteJson('/api/admin/areas/' . $id, [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('areas', ['id' => $id]);
    }

    public function test_areas_require_admin_auth(): void
    {
        $response = $this->getJson('/api/admin/areas');
        $response->assertStatus(401);
    }

    /** Ensure every area in list response has id, location, supervisor_id (values or null). */
    public function test_areas_list_each_item_has_all_keys_and_proper_types(): void
    {
        Area::factory()->create(['location' => 'Loc A']);
        Area::factory()->create(['location' => null]);
        $areaNoSupervisor = Area::factory()->create(['location' => 'No Sup']);
        $areaNoSupervisor->supervisors()->detach();
        $response = $this->getJson('/api/admin/areas?per_page=10', $this->authHeaders());
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('location', $item);
            $this->assertArrayHasKey('supervisor_id', $item);
            $this->assertIsInt($item['id']);
            if ($item['location'] !== null) {
                $this->assertIsString($item['location']);
            }
            $this->assertTrue($item['supervisor_id'] === null || is_int($item['supervisor_id']));
        }
    }

    /** Area with no supervisor returns supervisor_id null. */
    public function test_area_with_no_supervisor_returns_supervisor_id_null(): void
    {
        $area = Area::factory()->create(['location' => 'Unassigned']);
        $area->supervisors()->detach();
        $response = $this->getJson('/api/admin/areas/' . $area->id, $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $area->id);
        $response->assertJsonPath('data.location', 'Unassigned');
        $response->assertJsonPath('data.supervisor_id', null);
    }

    /** Area with null location in DB returns location null in response. */
    public function test_area_with_null_location_returns_location_null(): void
    {
        $area = Area::factory()->create(['location' => null, 'name' => 'No Location Area']);
        $response = $this->getJson('/api/admin/areas/' . $area->id, $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $area->id);
        $response->assertJsonPath('data.location', null);
        $this->assertArrayHasKey('supervisor_id', $response->json('data'));
    }

    /** Create/Show/Update responses have exactly id, location, supervisor_id. */
    public function test_area_single_response_has_only_expected_keys(): void
    {
        $area = Area::factory()->create(['location' => 'Keys Test']);
        $area->supervisors()->attach($this->supervisor->id);
        $response = $this->getJson('/api/admin/areas/' . $area->id, $this->authHeaders());
        $response->assertStatus(200);
        $data = $response->json('data');
        $expectedKeys = ['id', 'location', 'supervisor_id'];
        $this->assertEqualsCanonicalizing(array_keys($data), $expectedKeys);
        $this->assertSame($area->id, $data['id']);
        $this->assertSame('Keys Test', $data['location']);
        $this->assertSame($this->supervisor->id, $data['supervisor_id']);
    }
}
