<?php

namespace Tests\Feature\SupportChat;

use App\Enums\VendorStatus;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalSupportChatWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        foreach (['client', 'technician'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_client_can_load_widget_data_and_send_message(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $this->actingAs($client)
            ->getJson(route('client.support-chat.widget-data'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('session', null);

        $this->actingAs($client)
            ->postJson(route('client.support-chat.send'), ['message' => 'Hello support team'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($client)
            ->getJson(route('client.support-chat.widget-data'))
            ->assertOk()
            ->assertJsonPath('session.status', 'in_progress')
            ->assertJsonCount(1, 'messages');
    }

    public function test_technician_widget_routes_are_registered(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $technician->assignRole('technician');

        $this->actingAs($technician)
            ->postJson(route('technician.support-chat.send'), ['message' => 'Need help on site'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_vendor_is_notified_when_admin_closes_chat(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
        ]);

        $this->actingAs($vendorUser)
            ->postJson(route('vendor.support-chat.send'), ['message' => 'Please help'])
            ->assertOk();

        $session = SupportChatSession::query()->where('user_id', $vendorUser->id)->first();
        $this->assertNotNull($session);

        $this->actingAs($admin)
            ->putJson(route('admin.support-chat.update-status', $session), ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('session.is_closed', true);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $vendorUser->id,
            'type' => \App\Notifications\AdminNotification::class,
        ]);

        $this->actingAs($vendorUser)
            ->getJson(route('vendor.support-chat.widget-data'))
            ->assertOk()
            ->assertJsonPath('session.is_closed', true)
            ->assertJsonPath('can_send', false)
            ->assertJsonStructure(['closed_notice']);
    }
}
