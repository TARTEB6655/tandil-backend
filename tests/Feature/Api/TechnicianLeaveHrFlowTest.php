<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TechnicianLeaveHrFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;
    private User $supervisor;
    private User $areaManager;
    private User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->technician = User::factory()->create(['role' => 'technician', 'name' => 'Tech One']);
        $this->supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor One']);
        $this->areaManager = User::factory()->create(['role' => 'area_manager', 'name' => 'Area Manager One']);
        $this->hrUser = User::factory()->create(['role' => 'hr', 'name' => 'HR User']);
        $this->assignRoleIfAvailable($this->technician, 'technician');
        $this->assignRoleIfAvailable($this->supervisor, 'supervisor');
        $this->assignRoleIfAvailable($this->areaManager, 'area_manager');
        $this->assignRoleIfAvailable($this->hrUser, 'hr');

        $area = Area::factory()->create(['name' => 'AM Test Area']);
        DB::table('area_supervisor')->insert([
            ['area_id' => $area->id, 'user_id' => $this->supervisor->id],
            ['area_id' => $area->id, 'user_id' => $this->areaManager->id],
        ]);
        DB::table('area_technician')->insert([
            ['area_id' => $area->id, 'user_id' => $this->technician->id],
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
            //
        }
    }

    public function test_technician_leave_appears_in_hr_dashboard_summary(): void
    {
        $payload = [
            'leave_type' => 'Sick Leave',
            'start_date' => Carbon::today()->addDays(5)->toDateString(),
            'end_date' => Carbon::today()->addDays(7)->toDateString(),
            'reason' => 'Medical appointment',
        ];

        $create = $this->actingAs($this->technician, 'sanctum')
            ->postJson('/api/technician/leave-requests', $payload, [
                'Accept' => 'application/json',
            ]);
        $create->assertStatus(201)->assertJsonPath('success', true);
        $leaveId = $create->json('data.id');
        $this->assertNotNull($leaveId);

        $summary = $this->actingAs($this->hrUser, 'sanctum')
            ->getJson('/api/hr/dashboard/summary', ['Accept' => 'application/json']);
        $summary->assertStatus(200)->assertJsonPath('success', true);
        $pending = $summary->json('data.pending_leave_requests');
        $this->assertIsArray($pending);
        $found = collect($pending)->firstWhere('id', $leaveId);
        $this->assertNotNull($found, 'Technician leave request must appear in HR dashboard pending_leave_requests');
        $this->assertSame('Sick Leave', $found['leave_type'] ?? null);
        $this->assertSame($this->technician->name, $found['applicant_name'] ?? null);
    }

    public function test_technician_leave_appears_in_hr_leave_requests_index(): void
    {
        $payload = [
            'leave_type' => 'Annual Leave',
            'start_date' => Carbon::today()->addDays(10)->toDateString(),
            'end_date' => Carbon::today()->addDays(12)->toDateString(),
        ];

        $this->actingAs($this->technician, 'sanctum')
            ->postJson('/api/technician/leave-requests', $payload, [
                'Accept' => 'application/json',
            ])->assertStatus(201);

        $index = $this->actingAs($this->hrUser, 'sanctum')
            ->getJson('/api/hr/leave-requests?status=pending', ['Accept' => 'application/json']);
        $index->assertStatus(200)->assertJsonPath('success', true);
        $list = $index->json('data');
        $this->assertIsArray($list);
        $technicianLeave = collect($list)->first(function ($item) {
            return ($item['applicant_name'] ?? '') === $this->technician->name && ($item['leave_type'] ?? '') === 'Annual Leave';
        });
        $this->assertNotNull($technicianLeave, 'Technician leave must appear in HR leave-requests list');
    }

    public function test_hr_notifications_receive_technician_and_supervisor_leave_submissions(): void
    {
        $techPayload = [
            'leave_type' => 'Sick Leave',
            'start_date' => Carbon::today()->addDays(3)->toDateString(),
            'end_date' => Carbon::today()->addDays(4)->toDateString(),
            'reason' => 'Flu',
        ];
        $supPayload = [
            'leave_type' => 'Annual Leave',
            'start_date' => Carbon::today()->addDays(6)->toDateString(),
            'end_date' => Carbon::today()->addDays(7)->toDateString(),
            'reason' => 'Family trip',
        ];

        $this->actingAs($this->technician, 'sanctum')
            ->postJson('/api/technician/leave-requests', $techPayload, [
                'Accept' => 'application/json',
            ])->assertStatus(201);

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson('/api/supervisor/leave-requests', $supPayload, [
                'Accept' => 'application/json',
            ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->hrUser->id,
        ]);

        $hrList = $this->actingAs($this->hrUser, 'sanctum')
            ->getJson('/api/hr/notifications?per_page=20');

        $hrList->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = (array) $hrList->json('data.notifications.data');
        $this->assertGreaterThanOrEqual(2, count($items));

        $leaveItems = array_filter($items, static function (array $n): bool {
            $notificationType = (string) ($n['data']['type'] ?? '');
            $eventType = (string) ($n['data']['meta']['type'] ?? '');
            return $notificationType === 'admin_notification' && $eventType === 'hr_leave_request';
        });
        $this->assertGreaterThanOrEqual(2, count($leaveItems), 'HR must receive leave notifications for both technician and supervisor submissions.');

        $applicantIds = array_map(static function (array $n): int {
            return (int) ($n['data']['meta']['applicant_id'] ?? 0);
        }, $leaveItems);
        $this->assertContains($this->technician->id, $applicantIds);
        $this->assertContains($this->supervisor->id, $applicantIds);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->hrUser->id,
            'type' => 'App\\Notifications\\AdminNotification',
        ]);

        $amList = $this->actingAs($this->areaManager, 'sanctum')
            ->getJson('/api/area-manager/notifications?per_page=20');
        $amList->assertStatus(200)->assertJsonPath('success', true);
        $amItems = (array) $amList->json('data.notifications.data');
        $amLeaveItems = array_filter($amItems, static function (array $n): bool {
            return (string) ($n['data']['meta']['type'] ?? '') === 'area_manager_team_leave_request';
        });
        $this->assertGreaterThanOrEqual(2, count($amLeaveItems), 'Area manager must receive team leave notifications.');
    }
}
