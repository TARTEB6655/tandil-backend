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

        Notification::assertSentTo($admin, AdminNotification::class, function (AdminNotification $n) {
            $payload = $n->toArray(User::query()->where('role', 'admin')->first());

            return ($payload['title'] ?? null) === 'New Contractor Registration'
                && ($payload['meta']['entity'] ?? null) === 'supervisor_registration';
        });
        Notification::assertSentTo($user, SupervisorRegistrationStatusNotification::class);
    }

    public function test_admin_can_approve_reject_and_request_documents(): void
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
            ->assertJsonPath('data.status', 'documents_requested');

        Notification::assertSentTo($user, SupervisorRegistrationStatusNotification::class, function ($n) {
            return in_array($n->status, ['documents_requested', 'missing_documents'], true);
        });

        $this->withToken($token)
            ->postJson("/api/admin/supervisor-registrations/{$reg->id}/approve", [
                'notes' => 'All good',
                'employee_id' => 'SUP-100',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.employee_id', 'SUP-100');

        $this->assertSame('active', $user->fresh()->status);

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
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('inactive', $user2->fresh()->status);
    }

    public function test_admin_can_list_registrations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $user = User::factory()->create(['role' => 'supervisor', 'email' => 'list-sup@test.com']);
        SupervisorRegistration::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'name' => 'List Sup',
            'email' => $user->email,
            'company_name' => 'List Co',
        ]);

        $this->withToken($admin->createToken('t')->plainTextToken)
            ->getJson('/api/admin/supervisor-registrations?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.company_name', 'List Co');
    }
}
