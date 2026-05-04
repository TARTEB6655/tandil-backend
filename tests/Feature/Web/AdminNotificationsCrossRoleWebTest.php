<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationsCrossRoleWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_audience_filter_lists_that_recipients_notifications(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $technician = User::factory()->create(['role' => 'technician']);
        $technician->assignRole('technician');
        $this->assertTrue($technician->hasRole('technician'));

        $technician->notify(new AdminNotification('Tech broadcast title', 'Hello technician', []));

        $filtered = $this->actingAs($admin)->get(route('admin.notifications.index', ['audience_role' => 'technician']));
        $filtered->assertStatus(200);
        $filtered->assertSee('Hello technician', false);

        $withoutFilter = $this->actingAs($admin)->get(route('admin.notifications.index'));
        $withoutFilter->assertStatus(200);
        $withoutFilter->assertSee('Hello technician', false);
    }

    public function test_technician_filter_excludes_admin_personal_notifications(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $technician = User::factory()->create(['role' => 'technician']);
        $technician->assignRole('technician');

        $admin->notify(new AdminNotification('Admin only title', 'MSG_UNIQUE_ADMIN_991', []));
        $technician->notify(new AdminNotification('For tech', 'MSG_UNIQUE_TECH_882', []));

        $response = $this->actingAs($admin)
            ->get(route('admin.notifications.index', ['audience_role' => 'technician']));

        $response->assertStatus(200);
        $response->assertSee('MSG_UNIQUE_TECH_882', false);
        // Main list only: header dropdown also shows the admin’s own recent notification, so do not use assertDontSee on that text.
        $this->assertSame(1, substr_count($response->getContent(), 'class="notification-row border-b'));
    }

    /**
     * Legacy / odd payloads may only store audience on meta; SQL must match (not plain LIKE on full JSON).
     */
    public function test_audience_filter_matches_meta_only_audience_role(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => AdminNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $supervisor->id,
            'data' => json_encode([
                'message' => 'META_ONLY_AUDIENCE_MSG',
                'meta' => ['audience_role' => 'supervisor'],
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notifications.index', [
                'audience_role' => 'supervisor',
                'filter' => 'all',
                'kind' => '',
            ]))
            ->assertOk()
            ->assertSee('META_ONLY_AUDIENCE_MSG', false);
    }

    /** Narrow type filter + audience: no rows when nothing matches both (regression guard). */
    public function test_kind_tip_with_non_tip_notification_returns_empty_list(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $hr = User::factory()->create(['role' => 'hr']);
        $hr->assignRole('hr');

        $hr->notify(new AdminNotification('Hr title', 'Hr only body', []));

        $this->actingAs($admin)
            ->get(route('admin.notifications.index', [
                'audience_role' => 'hr',
                'kind' => 'tip',
                'filter' => 'all',
            ]))
            ->assertOk()
            ->assertSee('No notifications', false);
    }
}
