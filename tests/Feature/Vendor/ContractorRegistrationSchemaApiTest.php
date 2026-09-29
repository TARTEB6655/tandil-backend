<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\ContractorRegistrationField;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Notifications\VendorApplicationStatusNotification;
use Database\Seeders\ContractorRegistrationConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractorRegistrationSchemaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('vendor', 'web');
        $this->seed(ContractorRegistrationConfigSeeder::class);
    }

    public function test_public_registration_schema_returns_sections_and_options(): void
    {
        $response = $this->getJson('/api/contractor/auth/registration-schema');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'contractor-registration-v1')
            ->assertJsonPath('data.status_after_submit', 'pending');

        $sections = $response->json('data.sections');
        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections);
        $this->assertNotEmpty($response->json('data.options.banks'));
    }

    public function test_admin_can_toggle_field_required_and_schema_reflects_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $field = ContractorRegistrationField::query()->where('key', 'tax_vat_number')->firstOrFail();
        $this->withToken($token)
            ->putJson("/api/admin/contractor-registration/fields/{$field->id}", [
                'is_required' => true,
                'is_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.field.is_required', true);

        $schema = $this->getJson('/api/vendor/auth/registration-schema');
        $company = collect($schema->json('data.sections'))->firstWhere('key', 'company');
        $trn = collect($company['fields'] ?? [])->firstWhere('key', 'tax_vat_number');
        $this->assertTrue((bool) ($trn['is_required'] ?? false));
    }

    public function test_registration_sets_pending_and_sends_bilingual_submitted_notification(): void
    {
        Notification::fake();

        $response = $this->post('/api/contractor/auth/register', [
            'company_name' => 'Contractor Co',
            'authorized_person_name' => 'Sam Contractor',
            'email' => 'contractor-schema@test.com',
            'phone' => '+971500009901',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address' => 'Dubai Marina',
            'trade_license_number' => 'TL-CONTRACTOR',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'bank_name' => 'Emirates NBD',
            'iban' => 'AE070331234567890123456',
            'account_holder_name' => 'Contractor Co',
            'terms_accepted' => 1,
            'trade_license' => UploadedFile::fake()->create('trade-license.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', VendorStatus::Pending->value);

        $user = User::query()->where('email', 'contractor-schema@test.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VendorApplicationStatusNotification::class, function ($n) {
            $payload = $n->toArray($n->vendor->user);
            return ($payload['title'] ?? null) === 'Registration Under Review'
                && isset($payload['title_ar'], $payload['message_ar']);
        });
    }

    public function test_admin_can_request_missing_documents(): void
    {
        Notification::fake();

        $vendorUser = User::factory()->create(['role' => 'vendor', 'email' => 'missing-docs@test.com']);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create(['user_id' => $vendorUser->id, 'status' => VendorStatus::Pending->value]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Docs Co',
            'owner_name' => 'Docs Owner',
            'email' => $vendorUser->email,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->withToken($admin->createToken('t')->plainTextToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/request-documents", [
                'message' => 'Please upload VAT certificate.',
            ])
            ->assertOk();

        Notification::assertSentTo($vendorUser, VendorApplicationStatusNotification::class, function ($n) {
            return $n->status === 'missing_documents';
        });

        $this->assertNotNull($vendor->fresh()->profile->documents_requested_at);
        $this->assertSame('Please upload VAT certificate.', $vendor->fresh()->profile->admin_review_message);
    }
}
