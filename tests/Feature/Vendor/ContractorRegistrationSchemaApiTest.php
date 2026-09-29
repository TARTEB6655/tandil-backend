<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\ContractorRegistrationField;
use App\Models\Emirate;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Notifications\AdminNotification;
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

    public function test_public_schema_has_exact_contractor_fields_only(): void
    {
        $response = $this->getJson('/api/contractor/auth/registration-schema');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'contractor-registration-v2');

        $keys = collect($response->json('data.sections'))
            ->flatMap(fn ($s) => collect($s['fields'])->pluck('key'))
            ->sort()
            ->values()
            ->all();

        $expected = ContractorRegistrationConfigSeeder::ALLOWED_KEYS;
        sort($expected);
        $this->assertSame($expected, $keys);

        $this->assertSame(
            ['trade_license_upload', 'vat_certificate', 'bank_confirmation_letter'],
            $response->json('data.file_uploads')
        );
        $this->assertNotEmpty($response->json('data.admin_fields'));
    }

    public function test_admin_can_toggle_field_required_and_schema_reflects_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $field = ContractorRegistrationField::query()->where('key', 'trn')->firstOrFail();
        $this->withToken($token)
            ->putJson("/api/admin/contractor-registration/fields/{$field->id}", [
                'is_required' => true,
                'is_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.field.is_required', true);

        $schema = $this->getJson('/api/vendor/auth/registration-schema');
        $company = collect($schema->json('data.sections'))->firstWhere('key', 'company');
        $trn = collect($company['fields'] ?? [])->firstWhere('key', 'trn');
        $this->assertTrue((bool) ($trn['is_required'] ?? false));
    }

    public function test_contractor_registration_notifies_admin_and_contractor(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-contractor@test.com']);
        $admin->assignRole('admin');

        $emirate = Emirate::query()->firstOrCreate(
            ['slug' => 'dubai'],
            ['name' => 'Dubai', 'is_active' => true]
        );
        $category = Category::query()->firstOrCreate(
            ['slug' => 'cleaning-main'],
            ['name' => 'Cleaning', 'is_active' => true]
        );
        $service = Service::query()->firstOrCreate(
            ['slug' => 'deep-clean-contractor'],
            [
                'name' => 'Deep Clean',
                'is_active' => true,
                'category_id' => $category->id,
            ]
        );
        $area = Area::query()->firstOrCreate(
            ['name' => 'Marina Zone'],
            ['is_active' => true]
        );

        $response = $this->post('/api/contractor/auth/register', [
            'name' => 'Sam Contractor',
            'phone' => '+971500009901',
            'email' => 'contractor-schema@test.com',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'company_name' => 'Contractor Co',
            'trade_license_number' => 'TL-CONTRACTOR',
            'trade_license_upload' => UploadedFile::fake()->create('trade-license.pdf', 100, 'application/pdf'),
            'trade_license_expiry_date' => '2027-12-31',
            'trn' => 'TRN123',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'company_address' => 'Dubai Marina',
            'bank_name' => 'Emirates NBD',
            'account_holder_name' => 'Contractor Co',
            'bank_account_number' => '1234567890',
            'iban' => 'AE070331234567890123456',
            'bank_confirmation_letter' => UploadedFile::fake()->create('bank-letter.pdf', 100, 'application/pdf'),
            'main_service_categories' => [$category->id],
            'selected_services' => [$service->id],
            'emirates' => [$emirate->id],
            'service_coverage_areas' => [$area->id],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', VendorStatus::Pending->value);

        $user = User::query()->where('email', 'contractor-schema@test.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VendorApplicationStatusNotification::class);
        Notification::assertSentTo($admin, AdminNotification::class, function (AdminNotification $n) {
            $payload = $n->toArray(User::factory()->make(['role' => 'admin']));

            return str_contains((string) ($payload['title'] ?? ''), 'Contractor');
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
    }
}
