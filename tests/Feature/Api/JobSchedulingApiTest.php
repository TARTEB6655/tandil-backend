<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JobSchedulingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($this->admin, 'admin');
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

    public function test_admin_can_get_default_working_hours(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/working-hours')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.max_bookings_per_slot', 2)
            ->assertJsonPath('data.max_bookings_per_day', 12)
            ->assertJsonPath('data.buffer_minutes', 15)
            ->assertJsonCount(7, 'data.working_hours');
    }

    public function test_admin_can_update_working_hours(): void
    {
        $workingHours = collect(['mon', 'tue', 'wed', 'thu', 'sat', 'sun'])
            ->map(fn ($d) => ['day' => $d, 'enabled' => true, 'start' => '09:00', 'end' => '18:00'])
            ->push(['day' => 'fri', 'enabled' => false, 'start' => '09:00', 'end' => '18:00'])
            ->values()
            ->toArray();

        $res = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/job-scheduling/working-hours', [
                'working_hours' => $workingHours,
                'max_bookings_per_slot' => 3,
                'max_bookings_per_day' => 20,
                'buffer_minutes' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.max_bookings_per_slot', 3)
            ->assertJsonPath('data.max_bookings_per_day', 20)
            ->assertJsonPath('data.buffer_minutes', 30);

        $friday = collect($res->json('data.working_hours'))->firstWhere('day', 'fri');
        $this->assertFalse($friday['enabled']);
    }

    public function test_admin_can_update_working_hours_via_real_multipart_put(): void
    {
        // Regression guard: PHP does not populate $_POST for PUT + multipart/form-data,
        // so this simulates the raw wire body a Postman "form-data" PUT actually sends
        // (unlike putJson() above, which bypasses real body parsing entirely).
        $token = $this->admin->createToken('test', ['admin'])->plainTextToken;

        $boundary = '----JobSchedulingBoundary1';
        $fields = [
            'working_hours[0][day]' => 'mon',
            'working_hours[0][enabled]' => '1',
            'working_hours[0][start]' => '08:00',
            'working_hours[0][end]' => '17:00',
            'working_hours[1][day]' => 'tue',
            'working_hours[1][enabled]' => '0',
            'working_hours[1][start]' => '09:00',
            'working_hours[1][end]' => '18:00',
            'buffer_minutes' => '25',
        ];
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n"
                ."Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n"
                ."{$value}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $this->call(
            'PUT',
            '/api/admin/job-scheduling/working-hours',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            ],
            $body
        )
            ->assertOk()
            ->assertJsonPath('data.buffer_minutes', 25);

        $this->getJson('/api/admin/job-scheduling/working-hours', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.buffer_minutes', 25)
            ->assertJson(fn ($json) => $json->where('data.working_hours', function ($hours) {
                $mon = collect($hours)->firstWhere('day', 'mon');
                $tue = collect($hours)->firstWhere('day', 'tue');

                return $mon['start'] === '08:00' && $tue['enabled'] == false;
            })->etc());
    }

    public function test_non_admin_cannot_access_job_scheduling_settings(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/admin/job-scheduling/working-hours')
            ->assertStatus(403);
    }

    public function test_admin_can_add_list_toggle_and_delete_time_slots(): void
    {
        $add = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/job-scheduling/time-slots', [
                'start_time' => '12:00',
                'duration_minutes' => 60,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.start_time', '12:00')
            ->assertJsonPath('data.end_time', '13:00')
            ->assertJsonPath('data.is_active', true);

        $slotId = $add->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/job-scheduling/time-slots', ['start_time' => '12:00', 'duration_minutes' => 30])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/time-slots')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/job-scheduling/time-slots/{$slotId}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/job-scheduling/time-slots/{$slotId}")
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/time-slots')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_add_list_and_delete_blocked_dates(): void
    {
        $fullDay = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/job-scheduling/blocked-dates', [
                'date' => '2026-08-13',
                'block_type' => 'full_day',
                'reason' => 'Public holiday',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.block_type', 'full_day');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/job-scheduling/blocked-dates', [
                'date' => '2026-08-11',
                'block_type' => 'time_slot',
                'time' => '11:00',
                'reason' => 'Training session',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.time', '11:00');

        // time_slot block requires a time
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/job-scheduling/blocked-dates', [
                'date' => '2026-08-12',
                'block_type' => 'time_slot',
            ])
            ->assertStatus(422);

        $list = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/blocked-dates')
            ->assertOk();
        $this->assertCount(2, $list->json('data'));

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/admin/job-scheduling/blocked-dates/'.$fullDay->json('data.id'))
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/blocked-dates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_jobs_calendar_flags_technician_overlap(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($technician, 'technician');
        $area = Area::factory()->create(['is_active' => true]);
        DB::table('area_supervisor')->insert(['area_id' => $area->id, 'user_id' => $supervisor->id, 'created_at' => now(), 'updated_at' => now()]);

        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        Visit::create([
            'subscription_id' => $subscription->id,
            'technician_id' => $technician->id,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
        ]);
        // Overlaps with the 10:00 job (10:30 starts before 10:00+60min job ends)
        Visit::create([
            'subscription_id' => $subscription->id,
            'technician_id' => $technician->id,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:30',
            'status' => 'scheduled',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-10')
            ->assertOk()
            ->assertJsonPath('data.view', 'day')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.overlap_count', 2);

        $flags = collect($res->json('data.jobs'))->pluck('technician_overlap')->all();
        $this->assertSame([true, true], $flags);
    }

    public function test_jobs_calendar_week_view_returns_jobs_in_range(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        Visit::create([
            'subscription_id' => $subscription->id,
            'scheduled_date' => '2026-08-11',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=week&date=2026-08-10')
            ->assertOk()
            ->assertJsonPath('data.view', 'week')
            ->assertJsonPath('data.total', 1);
    }
}
