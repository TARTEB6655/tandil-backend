<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Visit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorAssignTasksFlowTest extends TestCase
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

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('smoke')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function seededNotes(string $farm, string $service, string $location, int $durationMin, float $price, array $clientParts): string
    {
        // Both supervisor and technician parseVisitMetaFromNotes() functions expect:
        // [DUMMY-SUP-ASSIGN] Farm | Service | Location | 120 min | AED 289.99 | 5/5 | Key: Value ...
        $kv = array_map(fn ($k, $v) => $k . ': ' . $v, array_keys($clientParts), array_values($clientParts));

        return '[DUMMY-SUP-ASSIGN] ' . implode(' | ', [
            $farm,
            $service,
            $location,
            $durationMin . ' min',
            'AED ' . number_format($price, 2, '.', ''),
            '5/5',
            ...$kv,
        ]);
    }

    public function test_supervisor_can_assign_and_technician_can_accept_or_reject(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor', 'email' => 'sup-flow@example.com']);
        $technician = User::factory()->create(['role' => 'technician', 'email' => 'tech-flow@example.com']);

        // Ensure both: (1) `users.role` column for our custom role middleware, and
        // (2) Spatie permission role (if tables exist) for any other code paths.
        $supervisor->role = 'supervisor';
        $technician->role = 'technician';
        $supervisor->save();
        $technician->save();

        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($technician, 'technician');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);
        $area->technicians()->attach($technician->id);

        $clientParts = [
            'Client' => 'Ahmad',
            'Email' => 'ahmad@test.com',
            'Phone' => '+971501111223',
            'Address' => 'Plot 10, Al Barsha South',
        ];

        $visitAccept = Visit::create([
            'subscription_id' => null,
            'technician_id' => null,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'scheduled_date' => Carbon::today()->toDateString(),
            'status' => 'pending',
            'notes' => $this->seededNotes('Water Pump Inspection', 'Hydro Pump', 'Al Barsha Farm Cluster', 45, 113.95, $clientParts),
            'price' => 113.95,
        ]);

        $visitReject = Visit::create([
            'subscription_id' => null,
            'technician_id' => null,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'scheduled_date' => Carbon::today()->toDateString(),
            'status' => 'pending',
            'notes' => $this->seededNotes('Irrigation Pipeline Check', 'Drip Irrigation', 'Mirdif Agriculture Block', 30, 95.0, $clientParts),
            'price' => 95.00,
        ]);

        // Supervisor sees available tasks (incoming card)
        $list = $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/supervisor/assign-tasks');
        $list->assertOk()->assertJsonPath('success', true);

        $available = collect($list->json('data.available_tasks', []));
        $this->assertTrue($available->contains(fn ($t) => (int) $t['id'] === (int) $visitAccept->id));
        $this->assertTrue($available->contains(fn ($t) => (int) $t['id'] === (int) $visitReject->id));

        // Supervisor assigns visitAccept to technician -> pending_acceptance
        $assignResp = $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/supervisor/assignments/' . $visitAccept->id, [
                'technician_id' => $technician->id,
            ]);
        $assignResp->assertOk()->assertJsonPath('success', true);

        $visitAccept->refresh();
        $this->assertSame($technician->id, $visitAccept->technician_id);
        $this->assertSame('pending_acceptance', $visitAccept->status);
        $this->assertNotNull($visitAccept->accept_by);

        // Technician accepts -> in_progress
        $this->assertSame('technician', (string) $technician->fresh()->role);
        $techAccept = $this->actingAs($technician, 'sanctum')
            ->postJson('/api/technician/tasks/' . $visitAccept->id . '/accept', []);
        $techAccept->assertOk()->assertJsonPath('success', true);

        $visitAccept->refresh();
        $this->assertSame('in_progress', $visitAccept->status);
        $this->assertNotNull($visitAccept->accepted_at);

        // Supervisor assigns visitReject to technician -> pending_acceptance
        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/supervisor/assignments/' . $visitReject->id, [
                'technician_id' => $technician->id,
            ])->assertOk();

        $visitReject->refresh();
        $this->assertSame('pending_acceptance', $visitReject->status);

        // Technician rejects -> should go back to pending and escalated if no next technician
        $techReject = $this->actingAs($technician, 'sanctum')
            ->postJson('/api/technician/tasks/' . $visitReject->id . '/reject', [
                'reason' => 'Not available',
            ]);
        $techReject->assertOk()->assertJsonPath('success', true);

        $visitReject->refresh();
        $this->assertSame('pending', $visitReject->status);
        $this->assertNull($visitReject->technician_id);
        $this->assertNotNull($visitReject->escalated_at);
    }
}

