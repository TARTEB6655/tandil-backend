<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(Role::class) && Schema::hasTable('roles')) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    /**
     * @return array{0: User, 1: Vendor}
     */
    private function createVendorUser(string $status = 'approved'): array
    {
        $user = User::factory()->create(['role' => 'vendor', 'email' => 'vendor-notify@test.com']);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('vendor');
        }

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Notify Vendor LLC',
            'owner_name' => 'Notify Owner',
            'email' => $user->email,
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
        ]);

        return [$user, $vendor];
    }

    public function test_vendor_can_list_notifications(): void
    {
        [$vendorUser] = $this->createVendorUser();
        $vendorUser->notify(new AdminNotification('Vendor hello', 'Please read this update.'));

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/vendor/notifications?per_page=20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'self')
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.data.0.data.title', 'Vendor hello');
    }

    public function test_under_review_vendor_cannot_access_notifications_until_approved(): void
    {
        [$vendorUser] = $this->createVendorUser('under_review');

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/vendor/notifications')
            ->assertForbidden();
    }

    public function test_vendor_notification_mutations_work(): void
    {
        [$vendorUser] = $this->createVendorUser();
        $vendorUser->notify(new AdminNotification('A', 'One'));
        $vendorUser->notify(new AdminNotification('B', 'Two'));
        $id = $vendorUser->notifications()->oldest()->value('id');

        $this->actingAs($vendorUser, 'sanctum')
            ->postJson("/api/vendor/notifications/{$id}/mark-read")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($vendorUser->notifications()->find($id)?->read_at);

        $this->actingAs($vendorUser, 'sanctum')
            ->deleteJson("/api/vendor/notifications/{$id}")
            ->assertOk();

        $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/vendor/notifications/clear-all')
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertSame(0, $vendorUser->fresh()->notifications()->count());
    }

    public function test_admin_broadcast_to_vendor_role_reaches_vendor_inbox(): void
    {
        if (! Schema::hasTable('admin_notification_broadcasts')) {
            $this->markTestSkipped('admin_notification_broadcasts migration not applied.');
        }

        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }

        [$vendorUser] = $this->createVendorUser();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/notifications/broadcast', [
                'title' => 'Vendor policy update',
                'message' => 'Please review the new vendor terms.',
                'type' => 'role',
                'role' => 'vendor',
            ])
            ->assertCreated()
            ->assertJsonPath('data.recipient_counts.vendors', 1);

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/vendor/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.data.0.data.title', 'Vendor policy update')
            ->assertJsonPath('data.notifications.data.0.data.meta.audience_role', 'vendor');
    }
}
