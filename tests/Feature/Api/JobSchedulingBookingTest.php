<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\JobTimeSlot;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JobSchedulingBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $supervisor;

    private User $technician;

    private Area $area;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($this->client, 'client');

        $this->supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($this->supervisor, 'supervisor');

        $this->technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($this->technician, 'technician');

        $this->area = Area::factory()->create(['is_active' => true]);
        DB::table('area_supervisor')->insert([
            'area_id' => $this->area->id,
            'user_id' => $this->supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->subscription = Subscription::factory()->create(['client_id' => $this->client->id]);

        JobTimeSlot::create(['start_time' => '10:00', 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        JobTimeSlot::create(['start_time' => '11:00', 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 2]);
        JobTimeSlot::create(['start_time' => '20:00', 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 3]); // outside default 09-18 hours
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

    /** 2026-08-10 is a Monday (a default-enabled working day, 09:00-18:00). */
    private const MON = '2026-08-10';

    public function test_available_slots_lists_active_slots_within_working_hours(): void
    {
        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/visits/available-slots?date='.self::MON)
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.date_blocked', false);

        $times = collect($res->json('data.slots'))->pluck('start_time')->all();
        $this->assertContains('10:00', $times);
        $this->assertContains('11:00', $times);
        $this->assertNotContains('20:00', $times, 'slot outside working hours should be excluded');
    }

    public function test_available_slots_empty_when_date_fully_blocked(): void
    {
        \App\Models\JobBlockedDate::create([
            'date' => self::MON, 'block_type' => 'full_day', 'reason' => 'Holiday',
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/visits/available-slots?date='.self::MON)
            ->assertOk()
            ->assertJsonPath('data.date_blocked', true)
            ->assertJsonPath('data.slots', []);
    }

    public function test_available_slots_marks_specific_slot_unavailable_when_time_blocked(): void
    {
        \App\Models\JobBlockedDate::create([
            'date' => self::MON, 'block_type' => 'time_slot', 'time' => '10:00', 'reason' => 'Training',
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/visits/available-slots?date='.self::MON)
            ->assertOk();

        $slots = collect($res->json('data.slots'))->keyBy('start_time');
        $this->assertFalse($slots['10:00']['available']);
        $this->assertTrue($slots['11:00']['available']);
    }

    public function test_available_slots_reflects_capacity_limit(): void
    {
        DB::table('job_scheduling_settings')->insert([
            'working_hours' => json_encode(\App\Models\JobSchedulingSetting::defaultWorkingHours()),
            'max_bookings_per_slot' => 1,
            'max_bookings_per_day' => 12,
            'buffer_minutes' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/visits/available-slots?date='.self::MON)
            ->assertOk();

        $slots = collect($res->json('data.slots'))->keyBy('start_time');
        $this->assertSame(0, $slots['10:00']['remaining']);
        $this->assertFalse($slots['10:00']['available']);
        $this->assertTrue($slots['11:00']['available']);
    }

    public function test_admin_blocked_time_slot_via_api_is_reflected_in_client_available_slots(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($admin, 'admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/job-scheduling/blocked-dates', [
                'date' => self::MON,
                'block_type' => 'time_slot',
                'time' => '10:00',
                'reason' => 'Training session',
            ])
            ->assertStatus(201);

        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/visits/available-slots?date='.self::MON)
            ->assertOk();

        $slots = collect($res->json('data.slots'))->keyBy('start_time');
        $this->assertFalse($slots['10:00']['available'], 'slot blocked via the admin API must be reflected as unavailable to the client');
        $this->assertTrue($slots['11:00']['available']);
    }

    public function test_create_visit_with_valid_slot_succeeds(): void
    {
        $res = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/visits', [
                'subscription_id' => $this->subscription->id,
                'area_id' => $this->area->id,
                'scheduled_date' => self::MON,
                'scheduled_time' => '10:00',
                'status' => 'pending',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.scheduled_time', '10:00');

        $this->assertDatabaseHas('visits', ['id' => $res->json('data.id'), 'scheduled_time' => '10:00']);
    }

    public function test_create_visit_rejects_slot_outside_working_hours(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/visits', [
                'subscription_id' => $this->subscription->id,
                'area_id' => $this->area->id,
                'scheduled_date' => self::MON,
                'scheduled_time' => '20:00',
                'status' => 'pending',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Selected time is outside working hours.');
    }

    public function test_create_visit_rejects_when_slot_fully_booked(): void
    {
        DB::table('job_scheduling_settings')->insert([
            'working_hours' => json_encode(\App\Models\JobSchedulingSetting::defaultWorkingHours()),
            'max_bookings_per_slot' => 1,
            'max_bookings_per_day' => 12,
            'buffer_minutes' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/visits', [
                'subscription_id' => $this->subscription->id,
                'area_id' => $this->area->id,
                'scheduled_date' => self::MON,
                'scheduled_time' => '10:00',
                'status' => 'pending',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This time slot is fully booked. Please choose another slot.');
    }

    public function test_create_visit_rejects_explicit_technician_conflict(): void
    {
        DB::table('area_technician')->insert([
            'area_id' => $this->area->id, 'user_id' => $this->technician->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
        ]);

        // 10:30 overlaps the existing 10:00-11:00 job for the same technician.
        JobTimeSlot::create(['start_time' => '10:30', 'duration_minutes' => 30, 'is_active' => true, 'sort_order' => 4]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/visits', [
                'subscription_id' => $this->subscription->id,
                'area_id' => $this->area->id,
                'technician_id' => $this->technician->id,
                'scheduled_date' => self::MON,
                'scheduled_time' => '10:30',
                'status' => 'pending',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Selected technician is already booked for this date and time.');
    }

    public function test_auto_dispatch_skips_technician_with_conflicting_slot(): void
    {
        $secondTechnician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($secondTechnician, 'technician');
        DB::table('area_technician')->insert([
            ['area_id' => $this->area->id, 'user_id' => $this->technician->id, 'created_at' => now(), 'updated_at' => now()],
            ['area_id' => $this->area->id, 'user_id' => $secondTechnician->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // First technician (alphabetically first by factory name may vary) already booked 10:00-11:00.
        Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/visits', [
                'subscription_id' => $this->subscription->id,
                'area_id' => $this->area->id,
                'scheduled_date' => self::MON,
                'scheduled_time' => '10:00',
                'status' => 'pending',
            ])
            ->assertStatus(201);

        $assignedTechnicianId = $res->json('data.technician_id');
        // Auto-dispatch must not offer it to the already-booked technician for the same slot.
        $this->assertNotEquals($this->technician->id, $assignedTechnicianId);
    }

    public function test_update_visit_reschedule_revalidates_slot(): void
    {
        $visit = Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'pending',
        ]);

        // Reschedule to a slot outside working hours should fail.
        $this->actingAs($this->client, 'sanctum')
            ->putJson('/api/visits/'.$visit->id, ['scheduled_time' => '20:00'])
            ->assertStatus(422);

        // Reschedule to a valid slot should succeed and persist.
        $this->actingAs($this->client, 'sanctum')
            ->putJson('/api/visits/'.$visit->id, ['scheduled_time' => '11:00'])
            ->assertOk()
            ->assertJsonPath('data.scheduled_time', '11:00');
    }

    public function test_update_visit_rejects_reassigning_conflicting_technician(): void
    {
        $secondTechnician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($secondTechnician, 'technician');

        Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'technician_id' => $secondTechnician->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $visitToReassign = Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'pending',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($admin, 'admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/visits/'.$visitToReassign->id, ['technician_id' => $secondTechnician->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Selected technician is already booked for this date and time.');
    }

    public function test_reschedule_sends_notification_to_client_and_technician(): void
    {
        Notification::fake();

        $visit = Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->putJson('/api/visits/'.$visit->id, ['scheduled_time' => '11:00'])
            ->assertOk();

        Notification::assertSentTo($this->client, AdminNotification::class, function (AdminNotification $n) {
            return ($n->toArray($this->client)['meta']['type'] ?? null) === 'booking_rescheduled';
        });
        Notification::assertSentTo($this->technician, AdminNotification::class, function (AdminNotification $n) {
            return ($n->toArray($this->technician)['meta']['type'] ?? null) === 'booking_rescheduled';
        });
    }

    public function test_create_visit_notifies_client_and_technician_of_booking_confirmed(): void
    {
        Notification::fake();

        DB::table('area_technician')->insert([
            'area_id' => $this->area->id, 'user_id' => $this->technician->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/visits', [
                'subscription_id' => $this->subscription->id,
                'area_id' => $this->area->id,
                'technician_id' => $this->technician->id,
                'scheduled_date' => self::MON,
                'scheduled_time' => '10:00',
                'status' => 'pending',
            ])
            ->assertStatus(201);

        Notification::assertSentTo($this->client, AdminNotification::class, function (AdminNotification $n) {
            return ($n->toArray($this->client)['meta']['type'] ?? null) === 'booking_confirmed';
        });
        Notification::assertSentTo($this->technician, AdminNotification::class, function (AdminNotification $n) {
            return ($n->toArray($this->technician)['meta']['type'] ?? null) === 'booking_confirmed';
        });
    }

    public function test_admin_can_view_booking_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($admin, 'admin');

        $visit = Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'scheduled',
            'notes' => 'Palm tree pruning | some extra context',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/jobs/'.$visit->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $visit->id)
            ->assertJsonPath('data.title', 'Palm tree pruning')
            ->assertJsonPath('data.client.name', $this->client->name)
            ->assertJsonPath('data.technician.name', $this->technician->name)
            ->assertJsonPath('data.scheduled_date', self::MON)
            ->assertJsonPath('data.scheduled_time', '10:00')
            ->assertJsonPath('data.end_time', '11:00')
            ->assertJsonPath('data.current_schedule_label', 'Currently Mon, Aug 10 · 10:00–11:00')
            ->assertJsonPath('data.technician_overlap', false);
    }

    public function test_booking_detail_returns_404_for_missing_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($admin, 'admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/jobs/999999')
            ->assertStatus(404);
    }

    public function test_admin_reschedule_with_custom_end_time_updates_duration_and_bypasses_slot_restriction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($admin, 'admin');

        $visit = Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '10:00',
            'status' => 'pending',
        ]);

        // 10:15 is not a configured JobTimeSlot — a normal client booking would be rejected,
        // but the admin Booking detail screen allows a free custom Start/End range.
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/visits/'.$visit->id, [
                'scheduled_time' => '10:15',
                'end_time' => '11:35',
            ])
            ->assertOk()
            ->assertJsonPath('data.scheduled_time', '10:15');

        $fresh = $visit->fresh();
        $this->assertSame('10:15', $fresh->scheduled_time);
        $this->assertSame(80, $fresh->duration_minutes);

        $detail = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/jobs/'.$visit->id)
            ->assertOk();
        $this->assertSame('11:35', $detail->json('data.end_time'));
    }

    public function test_admin_custom_duration_reschedule_still_blocks_technician_overlap(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($admin, 'admin');

        // Technician already has a long custom job 09:00-10:30.
        Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '09:00',
            'duration_minutes' => 90,
            'status' => 'scheduled',
        ]);

        $visitToReschedule = Visit::create([
            'subscription_id' => $this->subscription->id,
            'area_id' => $this->area->id,
            'supervisor_id' => $this->supervisor->id,
            'technician_id' => $this->technician->id,
            'scheduled_date' => self::MON,
            'scheduled_time' => '13:00',
            'status' => 'pending',
        ]);

        // Reschedule into a custom range that overlaps the technician's 09:00-10:30 job.
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/visits/'.$visitToReschedule->id, [
                'scheduled_time' => '10:00',
                'end_time' => '10:45',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Selected technician is already booked for this date and time.');
    }
}
