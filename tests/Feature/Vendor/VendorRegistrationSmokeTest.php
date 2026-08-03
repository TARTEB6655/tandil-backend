<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Jobs\OptimizePublicDiskImageJob;
use App\Models\User;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
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

    public function test_registration_defers_logo_optimization_after_response(): void
    {
        Bus::fake([OptimizePublicDiskImageJob::class]);
        Role::findOrCreate('vendor', 'web');

        $email = 'smoke-defer-'.uniqid().'@test.com';

        $this->post('/api/vendor/auth/register', $this->screenshotLikePayload($email), [
            'Accept' => 'application/json',
        ])->assertCreated();

        Bus::assertDispatched(OptimizePublicDiskImageJob::class, function (OptimizePublicDiskImageJob $job) {
            return $job->profile === 'vendor' && str_contains($job->relativePath, 'vendors/logos/');
        });
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
}
