<?php

namespace Tests\Feature\Vendor;

use App\Http\Requests\Vendor\VendorRegistrationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorRegistrationValidationProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        }
    }

    /** @return array<string, mixed> */
    private function basePayload(): array
    {
        return [
            'company_name' => 'Test LLC',
            'authorized_person_name' => 'Ali',
            'email' => 'probe-'.uniqid().'@test.com',
            'phone' => '+971500000001',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Addr',
            'trade_license_number' => 'TL-1',
            'vendor_type' => 'fruits',
            'emirate' => 'Dubai',
            'google_maps_location' => '25,55',
            'bank_name' => 'Bank',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Test',
            'delivery_radius' => 10,
            'opens_at' => '06:00',
            'closes_at' => '09:00',
            'minimum_order_amount' => 100,
            'terms_accepted' => 1,
            'trade_license' => UploadedFile::fake()->create('tl.pdf', 10, 'application/pdf'),
            'emirates_id' => UploadedFile::fake()->create('eid.pdf', 10, 'application/pdf'),
        ];
    }

    public function test_registration_accepts_mobile_time_formats_with_seconds_or_single_digit_hours(): void
    {
        $payload = $this->basePayload();
        $payload['opens_at'] = '6:00';
        $payload['closes_at'] = '9:00:00';

        $this->post('/api/vendor/auth/register', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.profile.operating_hours', '06:00 - 09:00');
    }

    public function test_registration_rejects_heic_logo_with_clear_message(): void
    {
        $payload = $this->basePayload();
        $payload['logo'] = UploadedFile::fake()->create('logo.heic', 50, 'image/heic');

        $response = $this->post('/api/vendor/auth/register', $payload, ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsStringIgnoringCase('heic', (string) $response->json('message'));
    }

    public function test_registration_accepts_document_file_aliases(): void
    {
        $request = \Illuminate\Http\Request::create('/api/vendor/auth/register', 'POST', [], [], [
            'trade_license_file' => UploadedFile::fake()->create('tl.pdf', 10, 'application/pdf'),
            'emirates_id_document' => UploadedFile::fake()->create('eid.pdf', 10, 'application/pdf'),
        ]);

        $form = VendorRegistrationRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));

        $aliasMethod = new \ReflectionMethod($form, 'normalizeRegistrationFileAliases');
        $aliasMethod->setAccessible(true);
        $aliasMethod->invoke($form);

        $this->assertInstanceOf(UploadedFile::class, $form->file('trade_license'));
        $this->assertInstanceOf(UploadedFile::class, $form->file('emirates_id'));
    }

    public function test_prepare_for_validation_normalizes_document_file_aliases(): void
    {
        $request = \Illuminate\Http\Request::create('/api/vendor/auth/register', 'POST', [], [], [
            'trade_license_file' => UploadedFile::fake()->create('tl.pdf', 10, 'application/pdf'),
            'emirates_id_document' => UploadedFile::fake()->create('eid.pdf', 10, 'application/pdf'),
        ]);

        $form = VendorRegistrationRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));

        $method = new \ReflectionMethod($form, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($form);

        $this->assertInstanceOf(UploadedFile::class, $form->file('trade_license'));
        $this->assertInstanceOf(UploadedFile::class, $form->file('emirates_id'));
    }

    public function test_registration_validation_returns_first_field_error_in_message(): void
    {
        $payload = $this->basePayload();
        unset($payload['trade_license'], $payload['emirates_id']);

        $this->postJson('/api/vendor/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Trade license document is required.')
            ->assertJsonValidationErrors(['trade_license', 'emirates_id']);
    }
}
