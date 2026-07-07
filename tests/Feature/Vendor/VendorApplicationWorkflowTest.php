<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorDocumentType;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorApplicationWorkflowTest extends TestCase
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

    public function test_pending_vendor_cannot_access_application_apis(): void
    {
        $user = $this->makeVendorUser(VendorStatus::Pending);
        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)->getJson('/api/vendor/application')->assertForbidden();
        $this->withToken($token)->postJson('/api/vendor/application/submit')->assertForbidden();
        $this->withToken($token)->post('/api/vendor/documents', [
            'type' => VendorDocumentType::BusinessLicense->value,
            'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_approved_vendor_can_view_application_status_api(): void
    {
        $user = $this->makeVendorUser(VendorStatus::Approved);
        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/vendor/application')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['status', 'completion_percent', 'required_documents']]);
    }

    public function test_admin_can_mark_vendor_under_review_and_disable(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $vendor = Vendor::create(['user_id' => User::factory()->create(['role' => 'vendor'])->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create(['vendor_id' => $vendor->id, 'business_name' => 'Co', 'owner_name' => 'O', 'email' => 'co@test.com']);

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/under-review")
            ->assertOk()
            ->assertJsonPath('data.vendor.status', VendorStatus::UnderReview->value);

        $vendor->update(['status' => VendorStatus::Approved->value, 'approved_at' => now()]);

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.vendor.status', VendorStatus::Disabled->value);
    }

    public function test_approved_vendor_can_upload_document_via_api(): void
    {
        $user = $this->makeVendorUser(VendorStatus::Approved);
        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)
            ->post('/api/vendor/documents', [
                'type' => VendorDocumentType::BusinessLicense->value,
                'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('vendor_documents', [
            'vendor_id' => $user->vendor->id,
            'type' => VendorDocumentType::BusinessLicense->value,
        ]);
    }

    private function makeVendorUser(VendorStatus $status): User
    {
        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password'), 'status' => 'active']);
        $user->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => $status->value,
            'approved_at' => $status === VendorStatus::Approved ? now() : null,
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        return $user->fresh('vendor');
    }
}
