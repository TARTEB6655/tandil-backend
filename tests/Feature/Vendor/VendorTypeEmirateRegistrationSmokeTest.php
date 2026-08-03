<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Emirate;
use App\Models\VendorType;
use Database\Seeders\VendorTypeAndEmirateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke: admin-managed types/emirates drive registration without timeouts/SQL leaks.
 */
class VendorTypeEmirateRegistrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(VendorTypeAndEmirateSeeder::class);
    }

    public function test_registration_accepts_admin_created_vendor_type_and_emirate(): void
    {
        VendorType::create(['name' => 'Spices', 'slug' => 'spices', 'is_active' => true]);
        Emirate::create(['name' => 'Al Ain Zone', 'slug' => 'al-ain-zone', 'is_active' => true]);

        $email = 'spice-vendor-'.uniqid().'@test.com';
        $started = microtime(true);

        $response = $this->post('/api/vendor/auth/register', [
            'company_name' => 'Spice Co',
            'authorized_person_name' => 'Ali',
            'email' => $email,
            'phone' => '+971501234999',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Al Ain',
            'trade_license_number' => 'TL-SPICE',
            'vendor_type' => 'Spices',
            'emirate' => 'al-ain-zone',
            'google_maps_location' => '24.2,55.7',
            'bank_name' => 'ADIB',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Ali',
            'delivery_radius' => '15',
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'minimum_order_amount' => '40',
            'terms_accepted' => '1',
            'trade_license' => UploadedFile::fake()->image('tl.jpg', 800, 800)->size(500),
            'emirates_id' => UploadedFile::fake()->image('eid.jpg', 800, 800)->size(500),
        ], ['Accept' => 'application/json']);

        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', VendorStatus::UnderReview->value)
            ->assertJsonPath('data.profile.vendor_type', 'spices')
            ->assertJsonPath('data.profile.emirate', 'Al Ain Zone');

        $this->assertLessThan(3000, $elapsedMs, "Registration took {$elapsedMs}ms");
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
    }

    public function test_inactive_vendor_type_is_rejected_or_falls_back_safely(): void
    {
        VendorType::query()->where('slug', 'meat')->update(['is_active' => false]);

        // Unknown/inactive "Meat" resolves via DB slug match even if inactive in resolveToSlug —
        // registration then validates against active slugs and falls back to "other".
        $response = $this->post('/api/vendor/auth/register', [
            'company_name' => 'Meat Co',
            'authorized_person_name' => 'Ali',
            'email' => 'meat-'.uniqid().'@test.com',
            'phone' => '+971501234888',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Dubai',
            'trade_license_number' => 'TL-MEAT',
            'vendor_type' => 'Meat',
            'emirate' => 'Dubai',
            'google_maps_location' => '25,55',
            'bank_name' => 'ADIB',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Ali',
            'delivery_radius' => '10',
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'minimum_order_amount' => '20',
            'terms_accepted' => '1',
            'trade_license' => UploadedFile::fake()->image('tl.jpg')->size(200),
            'emirates_id' => UploadedFile::fake()->image('eid.jpg')->size(200),
        ], ['Accept' => 'application/json']);

        // After resolve, slug is "meat" (inactive). Fallback should map to active "other".
        $response->assertCreated()
            ->assertJsonPath('data.profile.vendor_type', 'other');
    }

    public function test_inactive_emirate_cannot_be_used_on_register(): void
    {
        Emirate::query()->where('slug', 'dubai')->update(['is_active' => false]);

        $this->post('/api/vendor/auth/register', [
            'company_name' => 'Dubai Co',
            'authorized_person_name' => 'Ali',
            'email' => 'dxb-'.uniqid().'@test.com',
            'phone' => '+971501234777',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Dubai',
            'trade_license_number' => 'TL-DXB',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'google_maps_location' => '25,55',
            'bank_name' => 'ADIB',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Ali',
            'delivery_radius' => '10',
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'minimum_order_amount' => '20',
            'terms_accepted' => '1',
            'trade_license' => UploadedFile::fake()->image('tl.jpg')->size(200),
            'emirates_id' => UploadedFile::fake()->image('eid.jpg')->size(200),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['emirate']);
    }
}
