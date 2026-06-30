<?php

namespace Tests\Feature\Vendor;

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
            ->assertJsonPath('data.status', VendorStatus::Pending->value)
            ->assertJsonPath('data.profile.business_name', 'Green Farms LLC')
            ->assertJsonPath('data.profile.owner_name', 'Ali Vendor')
            ->assertJsonPath('data.profile.operating_hours', '08:00 - 22:00');

        $this->assertDatabaseHas('vendors', ['status' => 'pending']);
        $this->assertDatabaseHas('vendor_profiles', [
            'business_name' => 'Green Farms LLC',
            'trade_license_number' => 'TL-12345',
            'tax_vat_number' => 'TRN123456789',
        ]);
        $this->assertDatabaseHas('vendor_documents', ['type' => 'trade_license']);
        $this->assertDatabaseHas('vendor_documents', ['type' => 'emirates_id']);
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
