<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Notifications\VendorApplicationStatusNotification;
use App\Support\VendorRegistrationNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorRegistrationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        if (class_exists(Role::class) && Schema::hasTable('roles')) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

  /** @return array<string, mixed> */
    private function registerPayload(string $email): array
    {
        return [
            'company_name' => 'Notify Farms',
            'authorized_person_name' => 'Vendor Owner',
            'email' => $email,
            'phone' => '+971500000321',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Dubai',
            'trade_license_number' => 'TL-321',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'google_maps_location' => '25.2048,55.2708',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Notify Farms',
            'delivery_radius' => 10,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => 0,
            'terms_accepted' => 1,
            'trade_license' => UploadedFile::fake()->create('trade-license.pdf', 100, 'application/pdf'),
            'emirates_id' => UploadedFile::fake()->create('emirates-id.pdf', 100, 'application/pdf'),
        ];
    }

    public function test_registration_sends_received_then_under_review_notifications(): void
    {
        Notification::fake();

        $email = 'vendor-reg-notify-'.uniqid().'@test.com';

        $this->postJson('/api/vendor/auth/register', $this->registerPayload($email))
            ->assertCreated();

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);

        Notification::assertSentTo(
            $user,
            VendorApplicationStatusNotification::class,
            fn (VendorApplicationStatusNotification $n) => $n->status === 'registration_received'
                && str_contains($n->toArray($user)['message'], VendorRegistrationNotifications::registrationReceived()['message'])
        );

        $this->travel(3)->seconds();

        Notification::assertSentTo(
            $user,
            VendorApplicationStatusNotification::class,
            fn (VendorApplicationStatusNotification $n) => $n->status === 'registration_under_review'
        );
    }

    public function test_vendor_application_status_notification_includes_push_channel(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => 'pending']);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Push Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $notification = new VendorApplicationStatusNotification($vendor, 'approved');
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains(\App\Notifications\Channels\FcmChannel::class, $channels);
        $this->assertContains('mail', $channels);
    }
}