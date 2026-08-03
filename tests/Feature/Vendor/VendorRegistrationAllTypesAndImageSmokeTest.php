<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke: every vendor_type + ~5 MB image uploads succeed and store under 2 MB.
 */
class VendorRegistrationAllTypesAndImageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function vendorTypeProvider(): array
    {
        $cases = [];
        foreach (VendorType::cases() as $type) {
            // slug value as mobile may send
            $cases[$type->value.'_slug'] = [$type->value, $type->value];
            // display label casing as pickers often send
            $cases[$type->value.'_label'] = [$type->label(), $type->value];
        }

        return $cases;
    }

    #[DataProvider('vendorTypeProvider')]
    public function test_register_accepts_each_vendor_type_with_5mb_images(string $sentType, string $expectedStored): void
    {
        $email = 'type-'.preg_replace('/[^a-z0-9]+/i', '-', $sentType).'-'.uniqid().'@test.com';
        $phone = '+97150'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);

        // ~5 MB reported size (same class of upload the app sends).
        $fiveMb = 5 * 1024;

        $started = microtime(true);
        $response = $this->post('/api/vendor/auth/register', [
            'company_name' => 'Type Smoke '.$expectedStored,
            'authorized_person_name' => 'Owner '.$expectedStored,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Dubai',
            'trade_license_number' => 'TL-'.strtoupper($expectedStored).'-'.uniqid(),
            'vendor_type' => $sentType,
            'emirate' => 'Dubai',
            'google_maps_location' => '25.07,55.18',
            'bank_name' => 'ADIB',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Owner',
            'delivery_radius' => '25',
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'minimum_order_amount' => '50',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo.jpg', 2000, 2000)->size($fiveMb),
            'trade_license' => UploadedFile::fake()->image('tl.jpg', 2000, 2000)->size($fiveMb),
            'emirates_id' => UploadedFile::fake()->image('eid.jpg', 2000, 2000)->size($fiveMb),
        ], ['Accept' => 'application/json']);
        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', VendorStatus::UnderReview->value)
            ->assertJsonPath('data.profile.vendor_type', $expectedStored);

        $logoPath = (string) $response->json('data.profile.logo_path');
        $this->assertNotSame('', $logoPath);
        $this->assertTrue(Storage::disk('public')->exists($logoPath));
        $this->assertLessThanOrEqual(
            ImageCompressionService::MOBILE_UPLOAD_MAX_BYTES,
            Storage::disk('public')->size($logoPath),
            'Logo must be compressed under 2 MB before save'
        );

        $this->assertLessThan(5000, $elapsedMs, "Register ({$sentType}) took {$elapsedMs}ms");
    }

    public function test_five_mb_jpeg_is_within_allowed_upload_limit(): void
    {
        // Laravel max:102400 = 100 MB; 5 MB must never 422 on size alone.
        $email = 'size5mb-'.uniqid().'@test.com';

        $this->post('/api/vendor/auth/register', [
            'company_name' => 'Five MB Co',
            'authorized_person_name' => 'Owner',
            'email' => $email,
            'phone' => '+971509988776',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Dubai',
            'trade_license_number' => 'TL-5MB',
            'vendor_type' => 'vegetables',
            'emirate' => 'Sharjah',
            'google_maps_location' => '25.3,55.4',
            'bank_name' => 'ENBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Owner',
            'delivery_radius' => '10',
            'opens_at' => '09:00',
            'closes_at' => '21:00',
            'minimum_order_amount' => '20',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo.jpg', 1800, 1800)->size(5 * 1024),
            'trade_license' => UploadedFile::fake()->image('tl.jpg', 1800, 1800)->size(5 * 1024),
            'emirates_id' => UploadedFile::fake()->image('eid.jpg', 1800, 1800)->size(5 * 1024),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.profile.vendor_type', 'vegetables')
            ->assertJsonPath('data.profile.emirate', 'Sharjah');
    }
}
