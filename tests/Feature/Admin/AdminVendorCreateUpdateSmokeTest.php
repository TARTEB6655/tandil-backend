<?php

namespace Tests\Feature\Admin;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Database\Seeders\VendorTypeAndEmirateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke: admin create + full edit vendor (same fields as public registration).
 */
class AdminVendorCreateUpdateSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        $this->seed(VendorTypeAndEmirateSeeder::class);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->token = $this->admin->createToken('admin')->plainTextToken;
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    /** @return array<string, mixed> */
    private function fullRegistrationPayload(string $email, string $phone = '+971501234567'): array
    {
        return [
            'company_name' => 'Admin Created Farms LLC',
            'authorized_person_name' => 'Sara Admin Vendor',
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Industrial Area 1, Warehouse 5',
            'trade_license_number' => 'TL-ADMIN-001',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'google_maps_location' => '25.2048,55.2708',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Admin Created Farms LLC',
            'delivery_radius' => '25',
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => '50',
            'vat_number' => 'TRN999888777',
            'years_in_business' => '5',
            'description' => 'Fresh produce supplier',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo.jpg', 800, 800)->size(400),
            'trade_license' => UploadedFile::fake()->create('trade-license.jpg', 400, 'image/jpeg'),
            'emirates_id' => UploadedFile::fake()->create('emirates-id.jpg', 400, 'image/jpeg'),
        ];
    }

    public function test_admin_creates_vendor_with_registration_fields(): void
    {
        $email = 'admin-created-'.uniqid().'@test.com';

        $response = $this->post('/api/admin/vendors', $this->fullRegistrationPayload($email), $this->auth());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', VendorStatus::Approved->value)
            ->assertJsonPath('data.detail.summary.business_name', 'Admin Created Farms LLC')
            ->assertJsonPath('data.detail.contact.email', $email)
            ->assertJsonPath('data.detail.business_details.vendor_type', 'fruits')
            ->assertJsonPath('data.detail.business_details.emirate', 'Dubai')
            ->assertJsonPath('data.detail.business_details.years_in_business', 5)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'vendor_id',
                    'status',
                    'detail' => [
                        'vendor_id',
                        'summary',
                        'contact',
                        'business_details',
                        'bank_details',
                        'documents',
                    ],
                ],
            ]);

        $vendorId = (int) $response->json('data.vendor_id');
        $this->assertDatabaseHas('vendors', ['id' => $vendorId, 'status' => VendorStatus::Approved->value]);
        $this->assertDatabaseHas('vendor_profiles', [
            'vendor_id' => $vendorId,
            'business_name' => 'Admin Created Farms LLC',
            'operating_hours' => '08:00 - 22:00',
            'tax_vat_number' => 'TRN999888777',
        ]);
        $this->assertSame(2, VendorDocument::where('vendor_id', $vendorId)->count());
    }

    public function test_admin_can_create_vendor_as_under_review(): void
    {
        $email = 'admin-under-review-'.uniqid().'@test.com';
        $payload = $this->fullRegistrationPayload($email);
        $payload['status'] = 'under_review';

        $this->post('/api/admin/vendors', $payload, $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.status', VendorStatus::UnderReview->value);
    }

    public function test_admin_updates_all_vendor_profile_fields(): void
    {
        $email = 'admin-edit-'.uniqid().'@test.com';
        $create = $this->post('/api/admin/vendors', $this->fullRegistrationPayload($email), $this->auth())
            ->assertCreated();
        $vendorId = (int) $create->json('data.vendor_id');

        $newEmail = 'updated-'.uniqid().'@test.com';

        $update = $this->post('/api/admin/vendors/'.$vendorId, [
            'company_name' => 'Updated Farms LLC',
            'authorized_person_name' => 'Updated Owner',
            'email' => $newEmail,
            'phone' => '+971509998877',
            'vendor_type' => 'vegetables',
            'emirate' => 'Sharjah',
            'city' => 'Sharjah',
            'address' => 'New warehouse address',
            'trade_license_number' => 'TL-UPDATED',
            'google_maps_location' => '25.3,55.4',
            'bank_name' => 'ADCB',
            'iban' => 'AE070331234567890123499',
            'account_holder_name' => 'Updated Farms LLC',
            'delivery_radius' => '30',
            'opens_at' => '07:00',
            'closes_at' => '21:00',
            'minimum_order_amount' => '75',
            'vat_number' => 'TRN000111222',
            'years_in_business' => '8',
            'description' => 'Updated description',
            'password' => 'newpass12',
            'password_confirmation' => 'newpass12',
            'trade_license' => UploadedFile::fake()->create('new-license.pdf', 200, 'application/pdf'),
        ], $this->auth());

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.detail.summary.business_name', 'Updated Farms LLC')
            ->assertJsonPath('data.detail.business_details.vendor_type', 'vegetables')
            ->assertJsonPath('data.detail.business_details.emirate', 'Sharjah')
            ->assertJsonPath('data.detail.business_details.operating_hours', '07:00 - 21:00')
            ->assertJsonPath('data.detail.business_details.years_in_business', 8);

        $vendor = Vendor::with('profile', 'user')->findOrFail($vendorId);
        $this->assertSame('Updated Farms LLC', $vendor->profile->business_name);
        $this->assertSame('vegetables', $vendor->profile->vendor_type);
        $this->assertSame($newEmail, $vendor->user->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass12', $vendor->user->password));
    }

    public function test_admin_updates_vendor_status(): void
    {
        $email = 'admin-status-'.uniqid().'@test.com';
        $create = $this->post('/api/admin/vendors', array_merge($this->fullRegistrationPayload($email), [
            'status' => 'under_review',
        ]), $this->auth())
            ->assertCreated();
        $vendorId = (int) $create->json('data.vendor_id');

        $response = $this->post('/api/admin/vendors/'.$vendorId, [
            'status' => 'approved',
        ], $this->auth());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('vendors', ['id' => $vendorId, 'status' => 'approved']);
    }

    public function test_admin_updates_vendor_with_inactive_current_vendor_type(): void
    {
        $email = 'admin-inactive-'.uniqid().'@test.com';
        $create = $this->post('/api/admin/vendors', $this->fullRegistrationPayload($email), $this->auth())
            ->assertCreated();
        $vendorId = (int) $create->json('data.vendor_id');

        \App\Models\VendorType::query()->where('slug', 'fruits')->update(['is_active' => false]);

        $update = $this->post('/api/admin/vendors/'.$vendorId, [
            'company_name' => 'Updated Farms LLC',
            'vendor_type' => 'fruits',
        ], $this->auth());

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.detail.business_details.vendor_type', 'fruits');

        $vendor = Vendor::with('profile')->findOrFail($vendorId);
        $this->assertSame('Updated Farms LLC', $vendor->profile->business_name);
        $this->assertSame('fruits', $vendor->profile->vendor_type);
    }

    public function test_non_admin_cannot_create_vendor(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $token = $client->createToken('c')->plainTextToken;

        $this->post('/api/admin/vendors', $this->fullRegistrationPayload('blocked-'.uniqid().'@test.com'), [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(403);
    }

    public function test_admin_create_rejects_duplicate_email(): void
    {
        $email = 'dup-'.uniqid().'@test.com';
        User::factory()->create(['email' => $email]);

        $this->post('/api/admin/vendors', $this->fullRegistrationPayload($email), $this->auth())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);
    }
}
