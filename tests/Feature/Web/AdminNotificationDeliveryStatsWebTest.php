<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationDeliveryStatsWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_delivery_stats_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            if (method_exists($admin, 'assignRole')) {
                $admin->assignRole('admin');
            }
        }

        $response = $this->actingAs($admin)->get(route('admin.notifications.delivery-stats'));

        $response->assertStatus(200);
        $response->assertSee('Delivery analytics', false);
        $response->assertSee('Grand total', false);
    }

    public function test_non_admin_cannot_view_delivery_stats(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
            if (method_exists($client, 'assignRole')) {
                $client->assignRole('client');
            }
        }

        $this->actingAs($client)->get(route('admin.notifications.delivery-stats'))->assertStatus(403);
    }
}
