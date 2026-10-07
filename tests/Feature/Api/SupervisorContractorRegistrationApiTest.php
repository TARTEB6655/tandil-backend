<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Emirate;
use App\Models\Service;
use App\Models\SupervisorRegistration;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\SupervisorRegistrationStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorContractorRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('supervisor', 'web');
    }

    public function test_multipart_registration_creates_supervisor_not_vendor_and_notifies_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-sup-reg@test.com']);
        $admin->assignRole('admin');

        $emirate = Emirate::query()->firstOrCreate(['slug' => 'dubai'], ['name' => 'Dubai', 'is_active' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'cleaning-main'], ['name' => 'Cleaning', 'is_active' => true]);
        $service = Service::query()->firstOrCreate(
            ['slug' => 'deep-clean-sup'],
            ['name' => 'Deep Clean', 'is_active' => true, 'category_id' => $category->id]
        );
        $area = Area::query()->firstOrCreate(['name' => 'Marina Zone'], ['is_active' => true]);

        $response = $this->post('/api/contractor/auth/register', [
            'name' => 'Sam Supervisor',
            'phone' => '+971500009911',
            'email' => 'supervisor-contractor@test.com',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'company_name' => 'Field Co LLC',
            'trade_license_number' => 'TL-SUP-1',
            'trade_license_upload' => UploadedFile::fake()->create('trade-license.pdf', 100, 'application/pdf'),
            'trade_license_expiry_date' => '2027-12-31',
            'trn' => 'TRN999',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'company_address' => 'Dubai Marina',
            'bank_name' => 'Emirates NBD',
            'account_holder_name' => 'Field Co LLC',
            'bank_account_number' => '1234567890',
            'iban' => 'AE070331234567890123456',
            'bank_confirmation_letter' => UploadedFile::fake()->create('bank.pdf', 80, 'application/pdf'),
            'main_service_categories' => [$category->id],
            'selected_services' => [$service->id],
            'emirates' => [$emirate->id],
            'service_coverage_areas' => [$area->id],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Pending approval')
            ->assertJsonPath('data.company_name', 'Field Co LLC')
            ->assertJsonMissingPath('data.vendor_id');

        $user = User::query()->where('email', 'supervisor-contractor@test.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('supervisor', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertDatabaseMissing('vendors', ['user_id' => $user->id]);
        $this->assertDatabaseHas('supervisor_registrations', [
            'user_id' => $user->id,
            'status' => 'pending',
            'company_name' => 'Field Co LLC',
        ]);

        Notification::assertSentTo($admin, AdminNotification::class, function (AdminNotification $n) use ($admin) {
            $payload = $n->toArray($admin);

            return ($payload['title'] ?? null) === 'New Contractor Registration'
                && ($payload['meta']['entity'] ?? null) === 'supervisor_registration'
                && isset($payload['meta']['registration_id'])
                && ! isset($payload['meta']['vendor_id']);
        });
        Notification::assertSentTo($user, SupervisorRegistrationStatusNotification::class, function ($n) use ($user) {
            $payload = $n->toArray($user);

            return ($payload['title'] ?? null) === 'Registration Under Review'
                && ($payload['title_ar'] ?? null) === 'التسجيل قيد المراجعة'
                && ($payload['meta']['entity'] ?? null) === 'supervisor_registration';
        });
        Notification::assertNotSentTo($user, \App\Notifications\VendorApplicationStatusNotification::class);
    }

    public function test_admin_list_matches_contractor_management_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('t')->plainTextToken;

        $pendingUser = User::factory()->create(['role' => 'supervisor', 'status' => 'pending', 'email' => 'p1@test.com']);
        SupervisorRegistration::create([
            'user_id' => $pendingUser->id,
            'status' => 'pending',
            'name' => 'Ahmed Al Mansoori',
            'email' => 'ahmed.contractor@example.com',
            'company_name' => 'Al Barsha Maintenance LLC',
            'phone' => '+971501112233',
        ]);

        $activeUser = User::factory()->create(['role' => 'supervisor', 'status' => 'active', 'email' => 'a1@test.com']);
        SupervisorRegistration::create([
            'user_id' => $activeUser->id,
            'status' => 'approved',
            'name' => 'Active Person',
            'email' => $activeUser->email,
            'company_name' => 'Active Co',
        ]);

        $this->withToken($token)
            ->getJson('/api/admin/supervisor-registrations?status=pending')
            ->assertOk()
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.active', 1)
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.filters.pending', 1)
            ->assertJsonPath('data.filters.active', 1)
            ->assertJsonPath('data.filters.all', 2)
            ->assertJsonPath('data.items.0.company_name', 'Al Barsha Maintenance LLC')
            ->assertJsonPath('data.items.0.status', 'pending')
            ->assertJsonPath('data.items.0.status_label', 'Pending approval')
            ->assertJsonPath('data.items.0.status_badge', 'PENDING')
            ->assertJsonPath('data.items.0.contact_line', 'Ahmed Al Mansoori · ahmed.contractor@example.com')
            ->assertJsonPath('data.items.0.actions.approve', true)
            ->assertJsonPath('data.items.0.actions.cancel', true);
    }

    public function test_admin_detail_and_recent_match_application_ui(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('t')->plainTextToken;

        $user = User::factory()->create(['role' => 'supervisor', 'status' => 'pending', 'email' => 'ahmed.contractor@example.com']);
        $reg = SupervisorRegistration::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'name' => 'Ahmed Al Mansoori',
            'email' => 'ahmed.contractor@example.com',
            'phone' => '+971501112233',
            'company_name' => 'Al Barsha Maintenance LLC',
            'trade_license_number' => 'TL-NG_1-2024',
            'trade_license_expiry_date' => '2027-12-31',
            'trn' => '100123456700003',
            'emirate' => 'dubai',
            'city' => 'Dubai',
            'company_address' => 'Business Bay, Dubai, UAE',
        ]);
        $reg->documents()->create([
            'type' => 'trade_license',
            'file_path' => 'supervisors/1/documents/trade_license.pdf',
            'original_name' => 'trade_license.pdf',
            'verification_status' => 'pending',
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/supervisor-registrations/{$reg->id}")
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Al Barsha Maintenance LLC')
            ->assertJsonPath('data.status_label', 'Pending approval')
            ->assertJsonPath('data.personal.full_name', 'Ahmed Al Mansoori')
            ->assertJsonPath('data.personal.email', 'ahmed.contractor@example.com')
            ->assertJsonPath('data.personal.phone', '+971501112233')
            ->assertJsonPath('data.company.trade_license', 'TL-NG_1-2024')
            ->assertJsonPath('data.company.expiry', '2027-12-31')
            ->assertJsonPath('data.company.trn', '100123456700003')
            ->assertJsonPath('data.company.emirate', 'dubai')
            ->assertJsonPath('data.company.city', 'Dubai')
            ->assertJsonPath('data.company.address', 'Business Bay, Dubai, UAE')
            ->assertJsonPath('data.company.trade_license_file.name', 'trade_license.pdf')
            ->assertJsonPath('data.actions.approve', true)
            ->assertJsonPath('data.actions.reject', true)
            ->assertJsonPath('data.actions.delete', true);

        $this->withToken($token)
            ->getJson('/api/admin/supervisor-registrations/recent?limit=5')
            ->assertOk()
            ->assertJsonPath('data.total_pending', 1)
            ->assertJsonPath('data.items.0.company_name', 'Al Barsha Maintenance LLC')
            ->assertJsonPath('data.items.0.status_badge', 'PENDING')
            ->assertJsonPath('data.items.0.email', 'ahmed.contractor@example.com')
            ->assertJsonPath('data.view_all.endpoint', '/api/admin/supervisor-registrations');
    }

    public function test_admin_can_approve_reject_suspend_and_delete(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('t')->plainTextToken;

        $user = User::factory()->create([
            'role' => 'supervisor',
            'status' => 'pending',
            'email' => 'pending-sup@test.com',
        ]);
        $user->assignRole('supervisor');
        $reg = SupervisorRegistration::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'name' => 'Pending Sup',
            'email' => $user->email,
            'company_name' => 'Pending Co',
            'phone' => '+971500000001',
        ]);

        $this->withToken($token)
            ->postJson("/api/admin/supervisor-registrations/{$reg->id}/request-documents", [
                'message' => 'Upload VAT certificate.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.registration_status', 'documents_requested')
            ->assertJsonPath('data.status_label', 'Pending approval');

        $this->withToken($token)
            ->postJson("/api/admin/supervisor-registrations/{$reg->id}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.registration_status', 'approved')
            ->assertJsonPath('data.status_label', 'Active');

        $this->assertSame('active', $user->fresh()->status);

        $this->withToken($token)
            ->postJson("/api/admin/supervisor-registrations/{$reg->id}/account-status", [
                'action' => 'suspend',
                'notes' => 'Policy breach',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.status_label', 'Suspended');

        $this->assertSame('suspended', $user->fresh()->status);

        $this->withToken($token)
            ->postJson("/api/admin/supervisor-registrations/{$reg->id}/account-status", [
                'action' => 'activate',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $user2 = User::factory()->create(['role' => 'supervisor', 'status' => 'pending', 'email' => 'reject-sup@test.com']);
        $user2->assignRole('supervisor');
        $reg2 = SupervisorRegistration::create([
            'user_id' => $user2->id,
            'status' => 'pending',
            'name' => 'Reject Me',
            'email' => $user2->email,
            'company_name' => 'Reject Co',
        ]);

        $this->withToken($token)
            ->postJson("/api/admin/supervisor-registrations/{$reg2->id}/reject", [
                'reason' => 'Invalid license',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_label', 'Rejected');

        $this->assertSame('inactive', $user2->fresh()->status);

        $user3 = User::factory()->create(['role' => 'supervisor', 'status' => 'pending', 'email' => 'del-sup@test.com']);
        $reg3 = SupervisorRegistration::create([
            'user_id' => $user3->id,
            'status' => 'pending',
            'name' => 'Delete Me',
            'email' => $user3->email,
            'company_name' => 'Delete Co',
        ]);

        $this->withToken($token)
            ->deleteJson("/api/admin/supervisor-registrations/{$reg3->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('supervisor_registrations', ['id' => $reg3->id]);
        $this->assertDatabaseMissing('users', ['id' => $user3->id]);
    }
}
