<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitCreateApiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_create_visit_requires_resolvable_location_when_area_id_missing(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits', [
            'subscription_id' => $subscription->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Visit without area id',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Unable to resolve area from selected location. Send area_id or provide city/state or GPS coordinates.');
    }

    public function test_create_visit_auto_assigns_supervisor_from_area_when_not_provided(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create();
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits', [
            'subscription_id' => $subscription->id,
            'area_id' => $area->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Abu Dhabi test visit',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.supervisor_id', $supervisor->id);
    }

    public function test_resolve_area_by_city_name_for_location_step(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi',
            'location' => 'Abu Dhabi City',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits/resolve-area', [
            'city' => 'Abu Dhabi',
            'country' => 'UAE',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('serviceable', true)
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.supervisor_id', $supervisor->id);
    }

    public function test_create_visit_rejects_disabled_area(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'is_active' => false,
            'country' => 'UAE',
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits', [
            'subscription_id' => $subscription->id,
            'area_id' => $area->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }
}

