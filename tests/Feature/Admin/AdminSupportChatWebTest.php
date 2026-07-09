<?php

namespace Tests\Feature\Admin;

use App\Enums\VendorStatus;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSupportChatWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_vendor_live_chat_list(): void
    {
        $admin = $this->makeAdmin();
        $session = $this->makeVendorChatSession();

        $this->actingAs($admin)
            ->get(route('admin.support-chat.index'))
            ->assertOk()
            ->assertSee('Vendor Live Chat')
            ->assertSee($session->user->name);
    }

    public function test_admin_can_open_chat_and_reply(): void
    {
        $admin = $this->makeAdmin();
        $session = $this->makeVendorChatSession();

        SupportChatMessage::create([
            'support_chat_session_id' => $session->id,
            'user_id' => $session->user_id,
            'message' => 'Need help please',
            'is_admin' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.support-chat.show', $session))
            ->assertOk()
            ->assertSee('Need help please');

        $this->actingAs($admin)
            ->post(route('admin.support-chat.reply', $session), [
                'message' => 'We are here to help!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('support_chat_messages', [
            'support_chat_session_id' => $session->id,
            'message' => 'We are here to help!',
            'is_admin' => true,
        ]);
    }

    public function test_admin_can_poll_messages_json(): void
    {
        $admin = $this->makeAdmin();
        $session = $this->makeVendorChatSession();

        $msg = SupportChatMessage::create([
            'support_chat_session_id' => $session->id,
            'user_id' => $session->user_id,
            'message' => 'Hello',
            'is_admin' => false,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.support-chat.messages', $session).'?after_id='.$msg->id)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_view_vendor_analytics_page(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makeApprovedVendor();

        $this->actingAs($admin)
            ->get(route('admin.vendors.analytics', $vendor))
            ->assertOk()
            ->assertSee('Performance Analytics')
            ->assertSee($vendor->profile->business_name);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    private function makeVendorChatSession(): SupportChatSession
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $user->assignRole('vendor');

        return SupportChatSession::create([
            'user_id' => $user->id,
            'status' => 'open',
            'subject' => 'Live Chat with Admin',
        ]);
    }

    private function makeApprovedVendor(): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Farms',
            'owner_name' => 'Owner',
            'email' => $user->email,
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
        ]);

        return $vendor;
    }
}
