<?php

namespace Tests\Feature\Api;

use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TechnicianLeaveHrFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $technician;
    private User $hrUser;
    private string $techToken;
    private string $hrToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->technician = User::factory()->create(['role' => 'technician', 'name' => 'Tech One']);
        $this->hrUser = User::factory()->create(['role' => 'admin', 'name' => 'HR User']);
        $this->assignRoleIfAvailable($this->technician, 'technician');
        $this->assignRoleIfAvailable($this->hrUser, 'admin');
        $this->techToken = $this->technician->createToken('test')->plainTextToken;
        $this->hrToken = $this->hrUser->createToken('test')->plainTextToken;
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

        $create = $this->postJson('/api/technician/leave-requests', $payload, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->techToken,
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

        $this->postJson('/api/technician/leave-requests', $payload, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->techToken,
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
}
