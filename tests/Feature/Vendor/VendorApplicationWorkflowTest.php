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
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    public function test_vendor_can_view_application_status_api(): void
    {
        $user = $this->makePendingVendorUser();
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

    public function test_vendor_can_upload_document_via_api(): void
    {
        Storage::fake('public');
        $user = $this->makePendingVendorUser();
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

    public function test_vendor_cannot_submit_until_business_profile_complete(): void
    {
        Storage::fake('public');
        $user = $this->makePendingVendorUser();
        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        // Incomplete profile → submission blocked.
        $this->withToken($token)
            ->postJson('/api/vendor/application/submit')
            ->assertStatus(422);

        // Complete all required Business Profile fields + terms + logo via profile update.
        $category = \App\Models\Category::create([
            'name' => 'Fresh Produce',
            'slug' => 'fresh-produce-'.uniqid(),
            'is_active' => true,
        ]);

        $this->withToken($token)->post('/api/vendor/profile', [
            'business_name' => 'Green Farms',
            'owner_name' => 'Ali Vendor',
            'email' => $user->email,
            'phone' => '+971500000001',
            'trade_license_number' => 'TL-12345',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'address' => 'Industrial Area 1',
            'google_maps_location' => '25.2048,55.2708',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Green Farms LLC',
            'delivery_radius' => 15,
            'operating_hours' => 'Mon-Sun 9AM-9PM',
            'minimum_order_amount' => 50,
            'terms_accepted' => 1,
            'logo' => UploadedFile::fake()->image('logo.png'),
            'category_ids' => [$category->id],
        ])->assertOk();

        // Upload required documents (trade license + emirates id).
        foreach ([VendorDocumentType::TradeLicense->value, VendorDocumentType::EmiratesId->value] as $type) {
            $this->withToken($token)->post('/api/vendor/documents', [
                'type' => $type,
                'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
            ])->assertCreated();
        }

        // Now submission succeeds.
        $this->withToken($token)
            ->postJson('/api/vendor/application/submit')
            ->assertOk()
            ->assertJsonPath('data.profile_complete', true)
            ->assertJsonPath('data.documents_complete', true)
            ->assertJsonPath('data.completion_percent', 100);
    }

    private function makePendingVendorUser(): User
    {
        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $user->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Co',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        return $user->fresh('vendor');
    }
}
