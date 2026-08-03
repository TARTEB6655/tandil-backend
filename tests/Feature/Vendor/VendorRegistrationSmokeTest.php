<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Production-shaped smoke coverage for mobile Vendor Registration hangs / 500s.
 */
class VendorRegistrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @return array<string, mixed> */
    private function screenshotLikePayload(string $email): array
    {
        return [
            'company_name' => 'Smoke Vendor LLC',
            'authorized_person_name' => 'Ali Vendor',
            'email' => $email,
            'phone' => '+971501112233',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Industrial Area, Dubai',
            'trade_license_number' => 'TL-SMOKE-1',
            'vendor_type' => 'Fruits', // label casing from mobile pickers
            'emirate' => 'dubai', // lowercase from mobile
            'city' => 'Dubai',
            'google_maps_location' => '25.2048,55.2708',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => '2323323',
            'delivery_radius' => '25',
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => '34',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('store-logo.jpg', 2400, 2400)->size(4500),
            'trade_license' => UploadedFile::fake()->create('trade-license.jpg', 1800, 'image/jpeg'),
            'emirates_id' => UploadedFile::fake()->create('emirates-id.jpg', 1500, 'image/jpeg'),
        ];
    }

    public function test_registration_succeeds_when_spatie_vendor_role_was_never_seeded(): void
    {
        // Simulate a migrate-only deploy where RoleSeeder was never run.
        Role::query()->where('name', 'vendor')->delete();
        $this->assertDatabaseMissing('roles', ['name' => 'vendor']);

        $email = 'smoke-no-role-'.uniqid().'@test.com';

        $this->post('/api/vendor/auth/register', $this->screenshotLikePayload($email), [
            'Accept' => 'application/json',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', VendorRegistrationService::REGISTRATION_SUCCESS_MESSAGE)
            ->assertJsonPath('data.status', VendorStatus::UnderReview->value)
            ->assertJsonPath('data.profile.operating_hours', '08:00 - 22:00')
            ->assertJsonPath('data.profile.account_holder_name', '2323323')
            ->assertJsonPath('data.profile.vendor_type', 'fruits')
            ->assertJsonPath('data.profile.emirate', 'Dubai');

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('vendor'));
        $this->assertDatabaseHas('roles', ['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_registration_compresses_logo_under_2mb_before_save(): void
    {
        Role::findOrCreate('vendor', 'web');

        $email = 'smoke-compress-'.uniqid().'@test.com';

        $response = $this->post('/api/vendor/auth/register', $this->screenshotLikePayload($email), [
            'Accept' => 'application/json',
        ])->assertCreated();

        $logoUrl = (string) $response->json('data.logo_url');
        $this->assertNotSame('', $logoUrl);

        $relative = (string) \App\Models\VendorProfile::where('email', $email)->value('logo_path');
        $this->assertNotSame('', $relative);
        $this->assertTrue(Storage::disk('public')->exists($relative));
        $this->assertLessThanOrEqual(
            \App\Services\ImageCompressionService::MOBILE_UPLOAD_MAX_BYTES,
            Storage::disk('public')->size($relative)
        );
    }

    public function test_registration_accepts_extensionless_document_upload(): void
    {
        Role::findOrCreate('vendor', 'web');

        $email = 'smoke-ext-'.uniqid().'@test.com';
        $payload = $this->screenshotLikePayload($email);
        $payload['trade_license'] = UploadedFile::fake()->create('license', 800, 'image/jpeg');
        $payload['emirates_id'] = UploadedFile::fake()->create('idcard', 800, 'image/jpeg');

        $this->post('/api/vendor/auth/register', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', VendorStatus::UnderReview->value);
    }

    public function test_duplicate_phone_returns_friendly_422_not_sql(): void
    {
        Role::findOrCreate('vendor', 'web');

        User::factory()->create([
            'phone' => '0555381810',
            'email' => 'existing-phone-'.uniqid().'@test.com',
        ]);

        $payload = $this->screenshotLikePayload('hamood-'.uniqid().'@outlook.com');
        $payload['phone'] = '0555381810';

        $response = $this->post('/api/vendor/auth/register', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This phone number is already registered. Please log in or use a different phone number.')
            ->assertJsonValidationErrors(['phone']);

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
        $this->assertStringNotContainsString('Duplicate entry', (string) $response->json('message'));
    }

    public function test_unknown_vendor_type_falls_back_to_other_instead_of_422(): void
    {
        Role::findOrCreate('vendor', 'web');

        $email = 'smoke-type-'.uniqid().'@test.com';
        $payload = $this->screenshotLikePayload($email);
        $payload['vendor_type'] = 'Grocery Store'; // not in enum; mobile picker mismatch

        $this->post('/api/vendor/auth/register', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.profile.vendor_type', 'other');
    }

    public function test_business_type_alias_and_fruit_singular_resolve(): void
    {
        Role::findOrCreate('vendor', 'web');

        $email = 'smoke-alias-'.uniqid().'@test.com';
        $payload = $this->screenshotLikePayload($email);
        unset($payload['vendor_type']);
        $payload['business_type'] = 'Fruit';

        $this->post('/api/vendor/auth/register', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.profile.vendor_type', 'fruits');
    }

    public function test_registration_responds_quickly_without_blocking_on_admin_notify(): void
    {
        Role::findOrCreate('vendor', 'web');
        Role::findOrCreate('admin', 'web');
        User::factory()->create(['role' => 'admin', 'email' => 'admin-fast-'.uniqid().'@test.com'])
            ->assignRole('admin');

        $email = 'smoke-fast-'.uniqid().'@test.com';
        $started = microtime(true);

        $this->post('/api/vendor/auth/register', $this->screenshotLikePayload($email), [
            'Accept' => 'application/json',
        ])->assertCreated();

        $elapsedMs = (microtime(true) - $started) * 1000;

        // Hot path must stay under a couple seconds locally (uploads are fakes).
        $this->assertLessThan(2500, $elapsedMs, "Registration took {$elapsedMs}ms — expected a fast response.");
    }
}
