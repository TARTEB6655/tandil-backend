<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
