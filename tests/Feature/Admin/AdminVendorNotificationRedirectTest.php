<?php

namespace Tests\Feature\Admin;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorNotificationRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_vendor_registration_notification_redirects_to_vendor_profile(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makePendingVendor('Green Farms LLC');

        $admin->notify(new AdminNotification(
            'New Vendor Registration',
            'Green Farms LLC signed up and is awaiting review.',
            [
                'entity' => 'vendor',
                'vendor_id' => $vendor->id,
                'action' => 'new_registration',
            ]
        ));

        $notification = $admin->notifications()->first();

        $this->actingAs($admin)
            ->get(route('admin.notifications.read-and-redirect', $notification->id))
            ->assertRedirect(route('admin.vendors.show', $vendor));

        $this->actingAs($admin)
            ->get(route('admin.notifications.show', $notification->id))
            ->assertRedirect(route('admin.vendors.show', $vendor));
    }

    public function test_admin_vendor_application_notification_redirects_to_vendor_profile(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makePendingVendor('Resubmit Farms');

        $admin->notify(new AdminNotification(
            'Vendor Application Resubmitted',
            'Resubmit Farms updated their application.',
            [
                'entity' => 'vendor_application',
                'vendor_id' => $vendor->id,
            ]
        ));

        $notification = $admin->notifications()->first();

        $this->actingAs($admin)
            ->get(route('admin.notifications.read-and-redirect', $notification->id))
            ->assertRedirect(route('admin.vendors.show', $vendor));
    }

    public function test_admin_vendor_notification_redirects_even_when_vendor_is_soft_deleted(): void
    {
        $admin = $this->makeAdmin();
        $vendor = $this->makePendingVendor('Deleted Farms');
        $vendor->delete();

        $admin->notify(new AdminNotification(
            'New Vendor Registration',
            'Deleted Farms signed up and is awaiting review.',
            [
                'entity' => 'vendor',
                'vendor_id' => $vendor->id,
            ]
        ));

        $notification = $admin->notifications()->first();

        $this->actingAs($admin)
            ->get(route('admin.notifications.read-and-redirect', $notification->id))
            ->assertRedirect(route('admin.vendors.show', $vendor));

        $this->actingAs($admin)
            ->get(route('admin.vendors.show', $vendor))
            ->assertOk()
            ->assertSee('Deleted Farms');
    }

    public function test_missing_vendor_notification_redirects_to_vendor_list(): void
    {
        $admin = $this->makeAdmin();

        $admin->notify(new AdminNotification(
            'New Vendor Registration',
            'Missing vendor signed up.',
            [
                'entity' => 'vendor',
                'vendor_id' => 99999,
            ]
        ));

        $notification = $admin->notifications()->first();

        $this->actingAs($admin)
            ->get(route('admin.notifications.read-and-redirect', $notification->id))
            ->assertRedirect(route('admin.vendors.index', ['status' => 'pending']))
            ->assertSessionHas('error');
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        return $user;
    }

    private function makePendingVendor(string $businessName): Vendor
    {
        $user = User::factory()->create(['role' => 'vendor']);

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Pending->value,
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => $businessName,
            'owner_name' => 'Owner',
            'email' => $user->email,
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
        ]);

        return $vendor;
    }
}
