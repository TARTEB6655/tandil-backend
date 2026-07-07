<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Notifications\AdminNotification;
use App\Notifications\VendorApplicationStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    public function test_vendor_can_register_via_api_with_full_mobile_signup_payload(): void
    {
        $response = $this->post('/api/vendor/auth/register', [
            'company_name' => 'Green Farms LLC',
            'authorized_person_name' => 'Ali Vendor',
            'email' => 'vendor@test.com',
            'phone' => '+971500000001',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Industrial Area 1, Warehouse 5',
            'trade_license_number' => 'TL-12345',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'google_maps_location' => '25.2048,55.2708',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Green Farms LLC',
            'delivery_radius' => 25,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => 50,
            'vat_number' => 'TRN123456789',
            'terms_accepted' => 1,
            'logo' => UploadedFile::fake()->image('logo.png'),
            'trade_license' => UploadedFile::fake()->create('trade-license.pdf', 100, 'application/pdf'),
            'emirates_id' => UploadedFile::fake()->create('emirates-id.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', VendorStatus::UnderReview->value)
            ->assertJsonPath('data.profile.business_name', 'Green Farms LLC')
            ->assertJsonPath('data.profile.owner_name', 'Ali Vendor')
            ->assertJsonPath('data.profile.operating_hours', '08:00 - 22:00')
            ->assertJsonPath('data.logo_url', fn ($url) => is_string($url) && str_contains($url, '/media/vendors/logos/'))
            ->assertJsonPath('data.profile.logo_url', fn ($url) => is_string($url) && str_contains($url, '/media/vendors/logos/'))
            ->assertJsonPath('data.documents.0.file_url', fn ($url) => is_string($url) && str_contains($url, '/media/vendors/'));

        $this->assertDatabaseHas('vendors', ['status' => 'under_review']);
        $this->assertDatabaseHas('vendor_profiles', [
            'business_name' => 'Green Farms LLC',
            'trade_license_number' => 'TL-12345',
            'tax_vat_number' => 'TRN123456789',
        ]);
        $this->assertNotNull(VendorProfile::where('business_name', 'Green Farms LLC')->value('onboarding_completed_at'));
        $this->assertDatabaseHas('vendor_documents', ['type' => 'trade_license']);
        $this->assertDatabaseHas('vendor_documents', ['type' => 'emirates_id']);
    }

    public function test_vendor_registration_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-vendor-notify@test.com']);
        $admin->assignRole('admin');

        $this->post('/api/vendor/auth/register', [
            'company_name' => 'Notify Test LLC',
            'authorized_person_name' => 'Notify Vendor',
            'email' => 'notify-vendor@test.com',
            'phone' => '+971500000099',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Warehouse 9',
            'trade_license_number' => 'TL-NOTIFY',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'google_maps_location' => '25.2048,55.2708',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Notify Test LLC',
            'delivery_radius' => 10,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => 0,
            'terms_accepted' => 1,
            'logo' => UploadedFile::fake()->image('logo.png'),
            'trade_license' => UploadedFile::fake()->create('trade-license.pdf', 100, 'application/pdf'),
            'emirates_id' => UploadedFile::fake()->create('emirates-id.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        Notification::assertSentTo(
            $admin,
            AdminNotification::class,
            fn (AdminNotification $notification) => ($notification->toArray($admin)['title'] ?? null) === 'New Vendor Registration'
                && ($notification->toArray($admin)['meta']['entity'] ?? null) === 'vendor'
        );
    }

    public function test_vendor_signup_requires_trade_license_and_emirates_id_files(): void
    {
        $response = $this->postJson('/api/vendor/auth/register', [
            'business_name' => 'Green Farms',
            'owner_name' => 'Ali Vendor',
            'email' => 'missing-docs@test.com',
            'phone' => '+971500000002',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Street 1',
            'trade_license_number' => 'TL-999',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'google_maps_location' => '25.2048,55.2708',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Green Farms LLC',
            'delivery_radius' => 10,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => 0,
            'terms_accepted' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trade_license', 'emirates_id']);
    }

    public function test_admin_can_approve_vendor(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $user->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/admin/vendors/{$vendor->id}/approve", [
            'notes' => 'Looks good',
        ]);

        $response->assertOk()->assertJsonPath('data.vendor.status', VendorStatus::Approved->value);

        Notification::assertSentTo($user, VendorApplicationStatusNotification::class);
    }

    public function test_application_detail_shows_approved_status_after_admin_approves(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $user->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Status Test Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/approve", ['notes' => 'OK'])
            ->assertOk()
            ->assertJsonPath('data.vendor.status', VendorStatus::Approved->value)
            ->assertJsonPath('data.detail.summary.display_status', 'APPROVED')
            ->assertJsonPath('data.detail.summary.status', VendorStatus::Approved->value)
            ->assertJsonPath('data.detail.actions.can_approve', false);

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/application-detail")
            ->assertOk()
            ->assertJsonPath('data.summary.display_status', 'APPROVED')
            ->assertJsonPath('data.summary.status', VendorStatus::Approved->value)
            ->assertJsonPath('data.actions.can_approve', false);

        $this->withToken($token)
            ->getJson('/api/admin/vendors/recent-requests?limit=5')
            ->assertOk()
            ->assertJsonPath('data.total_pending', 0)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_admin_recent_vendor_requests_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $user1 = User::factory()->create(['role' => 'vendor', 'email' => 'recent1@test.com']);
        $user1->assignRole('vendor');
        Vendor::create(['user_id' => $user1->id, 'status' => VendorStatus::UnderReview->value]);
        VendorProfile::create([
            'vendor_id' => Vendor::where('user_id', $user1->id)->value('id'),
            'business_name' => 'Green Farms LLC',
            'owner_name' => 'Ali',
            'email' => 'recent1@test.com',
            'phone' => '+971501111111',
            'trade_license_number' => 'TL-RECENT-1',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'address' => 'Industrial Area 1',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Green Farms LLC',
            'delivery_radius' => 20,
            'operating_hours' => '08:00 - 22:00',
            'minimum_order_amount' => 50,
        ]);

        $user2 = User::factory()->create(['role' => 'vendor', 'email' => 'recent2@test.com']);
        $user2->assignRole('vendor');
        Vendor::create(['user_id' => $user2->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => Vendor::where('user_id', $user2->id)->value('id'),
            'business_name' => 'Fresh Harvest',
            'owner_name' => 'Khalid',
            'email' => 'recent2@test.com',
        ]);

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/admin/vendors/recent-requests?limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_pending', 2)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.display_status', 'PENDING');

        $greenFarms = collect($response->json('data.items'))->firstWhere('business_name', 'Green Farms LLC');
        $this->assertNotNull($greenFarms);
        $this->assertSame('fruits', $greenFarms['business_details']['vendor_type']);
        $this->assertSame('TL-RECENT-1', $greenFarms['business_details']['trade_license_number']);
        $this->assertSame('Dubai', $greenFarms['business_details']['emirate']);
        $this->assertSame('Emirates NBD', $greenFarms['bank_details']['bank_name']);
        $this->assertSame('Ali', $greenFarms['contact']['authorized_person_name']);

        $response->assertJsonStructure([
                'data' => [
                    'items' => [
                        [
                            'vendor_id',
                            'business_name',
                            'email',
                            'status',
                            'display_status',
                            'completion_percent',
                            'contact' => ['email', 'phone', 'authorized_person_name'],
                            'business_details' => [
                                'vendor_type',
                                'vendor_type_label',
                                'trade_license_number',
                                'emirate',
                                'city',
                                'address',
                                'delivery_radius',
                                'operating_hours',
                                'minimum_order_amount',
                                'categories',
                            ],
                            'bank_details' => ['bank_name', 'iban', 'account_holder_name'],
                            'documents',
                            'application',
                            'actions',
                        ],
                    ],
                    'total_pending',
                    'has_more',
                    'view_all',
                ],
            ]);
    }

    public function test_admin_vendor_application_detail_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $user = User::factory()->create(['role' => 'vendor', 'email' => 'detail-vendor@test.com', 'phone' => '+971501234567']);
        $user->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => VendorStatus::UnderReview->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Green Farms LLC',
            'owner_name' => 'Ali Vendor',
            'email' => 'detail-vendor@test.com',
            'phone' => '+971501234567',
            'trade_license_number' => 'TL-12345',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'address' => 'Industrial Area 1',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Green Farms LLC',
            'delivery_radius' => 25,
            'operating_hours' => '08:00 - 22:00',
            'minimum_order_amount' => 50,
        ]);

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $response = $this->withToken($token)->getJson("/api/admin/vendors/{$vendor->id}/application-detail");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Vendor application')
            ->assertJsonPath('data.summary.business_name', 'Green Farms LLC')
            ->assertJsonPath('data.summary.owner_name', 'Ali Vendor')
            ->assertJsonPath('data.summary.display_status', 'UNDER REVIEW')
            ->assertJsonPath('data.contact.email', 'detail-vendor@test.com')
            ->assertJsonPath('data.contact.authorized_person_name', 'Ali Vendor')
            ->assertJsonPath('data.business_details.trade_license_number', 'TL-12345')
            ->assertJsonPath('data.bank_details.bank_name', 'Emirates NBD')
            ->assertJsonPath('data.actions.can_approve', true)
            ->assertJsonPath('data.actions.can_reject', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['business_name', 'logo_url', 'status', 'submitted_at_formatted'],
                    'contact' => ['email', 'phone', 'authorized_person_name'],
                    'business_details' => ['vendor_type', 'trade_license_number', 'emirate', 'categories'],
                    'bank_details' => ['bank_name', 'iban', 'account_holder_name'],
                    'documents',
                    'application',
                    'actions',
                ],
            ]);
    }

    public function test_approved_vendor_cannot_access_other_vendor_product(): void
    {
        $v1User = User::factory()->create(['role' => 'vendor']);
        $v1User->assignRole('vendor');
        $v1 = Vendor::create(['user_id' => $v1User->id, 'status' => VendorStatus::Approved->value, 'approved_at' => now()]);
        VendorProfile::create(['vendor_id' => $v1->id, 'business_name' => 'V1', 'owner_name' => 'A', 'email' => 'v1@test.com']);

        $v2User = User::factory()->create(['role' => 'vendor']);
        $v2User->assignRole('vendor');
        $v2 = Vendor::create(['user_id' => $v2User->id, 'status' => VendorStatus::Approved->value, 'approved_at' => now()]);
        VendorProfile::create(['vendor_id' => $v2->id, 'business_name' => 'V2', 'owner_name' => 'B', 'email' => 'v2@test.com']);

        $token = $v1User->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)->getJson('/api/vendor/products/99999')->assertStatus(404);
    }
}
