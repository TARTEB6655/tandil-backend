<?php

namespace Tests\Feature\Api;

use App\Models\Area;
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

        $jobs = collect($res->json('data.jobs'));
        $this->assertTrue($jobs->every(fn ($j) => ($j['technician_overlap'] ?? false) === true));
        $this->assertTrue($jobs->every(fn ($j) => ($j['overlap_warning'] ?? null) === 'Technician overlap'));
        $this->assertNotEmpty($jobs->first()['time_slot'] ?? null);
        $this->assertNotEmpty($jobs->first()['overlap_with_job_ids'] ?? []);
        $this->assertSame($technician->name, $jobs->first()['technician_name'] ?? null);
        $this->assertSame($supervisor->name, $jobs->first()['supervisor_name'] ?? null);
        $this->assertStringContainsString($technician->name, (string) ($jobs->first()['assignees_label'] ?? ''));
        $this->assertStringContainsString($supervisor->name, (string) ($jobs->first()['assignees_label'] ?? ''));
    }

    public function test_jobs_calendar_backfills_slot_from_shop_order_item(): void
    {
        $admin = $this->admin;
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $area = Area::factory()->create(['is_active' => true]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\JobTimeSlot::query()->delete();
        \App\Models\JobTimeSlot::create([
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $category = \App\Models\Category::factory()->create();
        $product = \App\Models\Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'price' => 100,
        ]);

        $order = \App\Models\Order::factory()->create([
            'user_id' => $client->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'booking_date' => null,
            'booking_slot' => null,
        ]);

        $item = \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
            'booking_date' => '2026-08-21',
            'booking_slot' => '09:00 AM',
        ]);

        // Simulate old visit created before start-only slot parsing worked.
        Visit::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'scheduled_date' => '2026-08-20',
            'scheduled_time' => null,
            'duration_minutes' => null,
            'status' => 'pending',
            'notes' => $product->name.' | Order Service Visit | Abu Dhabi | -- min | AED 100.00 | [SHOP-ORDER:'.$order->id.'][ITEM:'.$item->id.']',
        ]);

        $res = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=month&date=2026-08-10')
            ->assertOk();

        $job = collect($res->json('data.jobs'))->firstWhere('id', Visit::query()->where('order_id', $order->id)->value('id'));
        $this->assertNotNull($job);
        $this->assertSame('2026-08-21', $job['scheduled_date']);
        $this->assertSame('09:00', $job['scheduled_time']);
        $this->assertSame('10:00', $job['end_time']);
        $this->assertNotNull($job['time_slot']);
    }

    public function test_jobs_calendar_backfills_slot_from_recreated_order_notes(): void
    {
        $admin = $this->admin;
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $area = Area::factory()->create(['is_active' => true]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\JobTimeSlot::query()->delete();
        \App\Models\JobTimeSlot::create([
            'start_time' => '15:00',
            'duration_minutes' => 120,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $category = \App\Models\Category::factory()->create();
        $product = \App\Models\Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'price' => 100,
        ]);

        $order = \App\Models\Order::factory()->create([
            'user_id' => $client->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'booking_date' => '2026-08-15',
            'booking_slot' => '03:00 PM',
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
            'booking_date' => '2026-08-15',
            'booking_slot' => '03:00 PM',
        ]);

        // Production-style orphan: only "Recreated from Order #N" in notes, no FKs.
        $visit = Visit::create([
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'scheduled_date' => '2026-08-15',
            'scheduled_time' => null,
            'duration_minutes' => null,
            'status' => 'pending',
            'notes' => 'Recreated from Order #'.$order->id,
        ]);

        $res = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=month&date=2026-08-10')
            ->assertOk();

        $job = collect($res->json('data.jobs'))->firstWhere('id', $visit->id);
        $this->assertNotNull($job);
        $this->assertSame('2026-08-15', $job['scheduled_date']);
        $this->assertSame('15:00', $job['scheduled_time']);
        $this->assertSame('17:00', $job['end_time']);
        $this->assertNotNull($job['time_slot']);

        $visit->refresh();
        $this->assertSame($order->id, (int) $visit->order_id);
        $this->assertSame('15:00', $visit->scheduled_time);
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

    public function test_admin_can_update_booking_detail_via_real_multipart_put_and_notifies_on_reschedule(): void
    {
        Notification::fake();

        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($technician, 'technician');
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $visit = Visit::create([
            'subscription_id' => $subscription->id,
            'technician_id' => $technician->id,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'notes' => 'Palm tree pruning | Mousa Al Baloushi',
        ]);

        $token = $this->admin->createToken('test', ['admin'])->plainTextToken;

        $boundary = '----BookingDetailBoundary1';
        $fields = [
            'date' => '2026-08-11',
            'start' => '11:00',
            'end' => '12:00',
            'technician_id' => (string) $technician->id,
            'internal_notes' => 'Gate code 4521, access from the back.',
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
            "/api/admin/job-scheduling/jobs/{$visit->id}",
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
            ->assertJsonPath('data.scheduled_date', '2026-08-11')
            ->assertJsonPath('data.scheduled_time', '11:00')
            ->assertJsonPath('data.end_time', '12:00')
            ->assertJsonPath('data.title', 'Palm tree pruning')
            ->assertJsonPath('data.notes', 'Palm tree pruning | Mousa Al Baloushi')
            ->assertJsonPath('data.internal_notes', 'Gate code 4521, access from the back.');

        $visit->refresh();
        $this->assertSame('2026-08-11', $visit->scheduled_date->toDateString());
        $this->assertSame('11:00', $visit->scheduled_time);
        $this->assertSame(60, $visit->duration_minutes);
        // internal_notes must never clobber the client-facing notes/title string.
        $this->assertSame('Palm tree pruning | Mousa Al Baloushi', $visit->notes);
        $this->assertSame('Gate code 4521, access from the back.', $visit->internal_notes);

        Notification::assertSentTo($client, AdminNotification::class);
        Notification::assertSentTo($technician, AdminNotification::class);
    }

    public function test_orphan_jobs_preview_and_delete_never_touches_real_bookings(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($technician, 'technician');
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        // A real, fully-formed booking - must survive both preview and delete.
        $realVisit = Visit::create([
            'subscription_id' => $subscription->id,
            'technician_id' => $technician->id,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'status' => 'pending',
            'notes' => 'Palm tree pruning | Mousa Al Baloushi',
        ]);

        // A date-only booking with a real subscription but no time/notes yet -
        // still has subscription_id, so it must never be treated as an orphan.
        $dateOnlyVisit = Visit::create([
            'subscription_id' => $subscription->id,
            'scheduled_date' => '2026-08-12',
            'status' => 'pending',
        ]);

        // Genuine junk: no subscription, no notes, no time, no assignment - matches
        // the exact pattern found cluttering the live Jobs calendar.
        $orphan1 = Visit::create(['scheduled_date' => '2026-08-05', 'status' => 'pending']);
        $orphan2 = Visit::create(['scheduled_date' => '2026-08-06', 'status' => 'pending']);

        // Same blank shape but already rejected - must NOT be deleted (status filter).
        $rejectedBlank = Visit::create(['scheduled_date' => '2026-08-07', 'status' => 'rejected']);

        $preview = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/jobs/orphans')
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $previewIds = $preview->json('data.ids');
        sort($previewIds);
        $this->assertSame([$orphan1->id, $orphan2->id], $previewIds);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/admin/job-scheduling/jobs/orphans')
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertDatabaseMissing('visits', ['id' => $orphan1->id]);
        $this->assertDatabaseMissing('visits', ['id' => $orphan2->id]);
        $this->assertDatabaseHas('visits', ['id' => $realVisit->id]);
        $this->assertDatabaseHas('visits', ['id' => $dateOnlyVisit->id]);
        $this->assertDatabaseHas('visits', ['id' => $rejectedBlank->id]);

        // Second run is a no-op - nothing left to delete.
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/jobs/orphans')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }
}
