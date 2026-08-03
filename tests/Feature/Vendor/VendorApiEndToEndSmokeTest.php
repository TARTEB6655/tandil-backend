<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Full vendor API smoke: register → errors → approve → login → me → profile → gated catalog.
 */
class VendorApiEndToEndSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /** @return array<string, mixed> */
    private function registerPayload(string $email, string $phone = '+971501112233'): array
    {
        return [
            'company_name' => 'E2E Fresh Market',
            'authorized_person_name' => 'E2E Owner',
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Dubai Industrial Area',
            'trade_license_number' => 'TL-E2E-1',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'google_maps_location' => '25.07428,55.18856',
            'bank_name' => 'ADIB',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'E2E Owner',
            'delivery_radius' => '25',
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => '50',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo.jpg', 1200, 1200)->size(2000),
            'trade_license' => UploadedFile::fake()->image('tl.jpg', 1200, 1600)->size(1800),
            'emirates_id' => UploadedFile::fake()->image('eid.jpg', 1200, 1600)->size(1500),
        ];
    }

    public function test_register_login_me_profile_catalog_gate_end_to_end(): void
    {
        $email = 'e2e-vendor-'.uniqid().'@test.com';

        // 1) Register — must be fast and return a clear success payload
        $started = microtime(true);
        $register = $this->post('/api/vendor/auth/register', $this->registerPayload($email), [
            'Accept' => 'application/json',
        ]);
        $registerMs = (microtime(true) - $started) * 1000;

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', VendorRegistrationService::REGISTRATION_SUCCESS_MESSAGE)
            ->assertJsonPath('data.status', VendorStatus::UnderReview->value)
            ->assertJsonPath('data.profile.business_name', 'E2E Fresh Market')
            ->assertJsonPath('data.profile.operating_hours', '08:00 - 22:00')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'vendor_id',
                    'status',
                    'logo_url',
                    'profile',
                    'documents',
                    'completion_percent',
                ],
            ]);
        $this->assertLessThan(3000, $registerMs, "Register took {$registerMs}ms");

        $vendorId = (int) $register->json('data.vendor_id');
        $this->assertGreaterThan(0, $vendorId);

        // Logo stored under 2 MB
        $logoPath = (string) $register->json('data.profile.logo_path');
        if ($logoPath !== '') {
            $this->assertTrue(Storage::disk('public')->exists($logoPath));
            $this->assertLessThanOrEqual(2 * 1024 * 1024, Storage::disk('public')->size($logoPath));
        }

        // 2) Under-review vendor cannot login yet — clear error (not timeout/SQL)
        $denied = $this->postJson('/api/vendor/auth/login', [
            'email' => $email,
            'password' => 'secret12',
            'roles' => 'vendor',
        ]);
        $denied->assertStatus(403)
            ->assertJsonPath('success', false);
        $this->assertNotEmpty($denied->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', (string) $denied->json('message'));

        // 3) Approve vendor (admin workflow)
        $vendor = Vendor::query()->findOrFail($vendorId);
        $vendor->update([
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        // 4) Login after approval
        $started = microtime(true);
        $login = $this->postJson('/api/vendor/auth/login', [
            'email' => $email,
            'password' => 'secret12',
            'roles' => 'vendor',
        ]);
        $loginMs = (microtime(true) - $started) * 1000;

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.vendor.is_approved', true)
            ->assertJsonStructure([
                'data' => ['token', 'user', 'vendor'],
            ]);
        $this->assertLessThan(2000, $loginMs, "Login took {$loginMs}ms");

        $token = (string) $login->json('data.token');
        $this->assertNotSame('', $token);

        // 5) /auth/me
        $this->withToken($token)->getJson('/api/vendor/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.vendor.vendor_id', $vendorId)
            ->assertJsonPath('data.vendor.is_approved', true);

        // 6) Profile GET
        $started = microtime(true);
        $profile = $this->withToken($token)->getJson('/api/vendor/profile');
        $profileMs = (microtime(true) - $started) * 1000;
        $profile->assertOk()->assertJsonPath('success', true);
        $this->assertLessThan(2000, $profileMs, "Profile GET took {$profileMs}ms");

        // 7) Approved vendor can list products (empty ok)
        $this->withToken($token)->getJson('/api/vendor/products')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_register_validation_errors_are_clear_and_fast(): void
    {
        $email = 'e2e-bad-'.uniqid().'@test.com';
        $payload = $this->registerPayload($email);
        unset($payload['trade_license'], $payload['emirates_id']);

        $started = microtime(true);
        $response = $this->post('/api/vendor/auth/register', $payload, ['Accept' => 'application/json']);
        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['trade_license', 'emirates_id']);
        $this->assertStringContainsStringIgnoringCase('trade license', (string) $response->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
        $this->assertLessThan(1500, $elapsedMs, "Validation error took {$elapsedMs}ms");
    }

    public function test_duplicate_phone_register_returns_friendly_422(): void
    {
        User::factory()->create([
            'role' => 'client',
            'phone' => '0555381810',
            'email' => 'existing-'.uniqid().'@test.com',
            'password' => Hash::make('secret12'),
        ]);

        $response = $this->post(
            '/api/vendor/auth/register',
            $this->registerPayload('dup-'.uniqid().'@test.com', '0555381810'),
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['phone']);
        $this->assertStringContainsStringIgnoringCase('phone', (string) $response->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
    }

    public function test_unauthenticated_vendor_profile_returns_401_json(): void
    {
        $this->getJson('/api/vendor/profile')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
