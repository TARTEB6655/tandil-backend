<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\Emirate;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Models\VendorProfile;
use App\Notifications\AdminNotification;
use App\Notifications\VendorApplicationStatusNotification;
use Database\Seeders\ContractorRegistrationConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Contractor registration = single multipart API: POST /api/contractor/auth/register
 */
class ContractorRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('vendor', 'web');
        $this->seed(ContractorRegistrationConfigSeeder::class);
    }

    public function test_contractor_register_route_is_the_only_public_contractor_auth_register_api(): void
    {
        $this->assertTrue(
            collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
                fn ($route) => $route->uri() === 'api/contractor/auth/register'
                    && in_array('POST', $route->methods(), true)
            )
        );

        $this->getJson('/api/contractor/auth/registration-schema')->assertNotFound();
        $this->getJson('/api/contractor/auth/registration-options')->assertNotFound();
    }

    public function test_multipart_registration_saves_data_and_notifies_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-contractor-notify@test.com']);
        $admin->assignRole('admin');

        $emirate = Emirate::query()->firstOrCreate(
            ['slug' => 'dubai'],
            ['name' => 'Dubai', 'is_active' => true]
        );
        $category = Category::query()->firstOrCreate(
            ['slug' => 'cleaning-main'],
            ['name' => 'Cleaning', 'is_active' => true]
        );
        $sub = Category::query()->firstOrCreate(
            ['slug' => 'home-cleaning-sub'],
            ['name' => 'Home Cleaning', 'is_active' => true, 'parent_id' => $category->id]
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

        $payload = [
            'name' => 'Sam Contractor',
            'phone' => '+971500009901',
            'email' => 'contractor-multipart@test.com',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'company_name' => 'Contractor Co LLC',
            'trade_license_number' => 'TL-CONTRACTOR-1',
            'trade_license_upload' => UploadedFile::fake()->create('trade-license.pdf', 120, 'application/pdf'),
            'trade_license_expiry_date' => '2027-12-31',
            'trn' => 'TRN123456',
            'vat_certificate' => UploadedFile::fake()->create('vat.pdf', 80, 'application/pdf'),
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'company_address' => 'Dubai Marina, WH-2',
            'bank_name' => 'Emirates NBD',
            'account_holder_name' => 'Contractor Co LLC',
            'bank_account_number' => '1234567890',
            'iban' => 'AE070331234567890123456',
            'bank_confirmation_letter' => UploadedFile::fake()->create('bank-letter.pdf', 90, 'application/pdf'),
            'main_service_categories' => [$category->id],
            'service_subcategories' => [$sub->id],
            'selected_services' => [$service->id],
            'emirates' => [$emirate->id],
            'service_coverage_areas' => [$area->id],
        ];

        $response = $this->post('/api/contractor/auth/register', $payload, [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', VendorStatus::Pending->value)
            ->assertJsonPath('data.profile.business_name', 'Contractor Co LLC')
            ->assertJsonPath('data.profile.owner_name', 'Sam Contractor')
            ->assertJsonPath('data.profile.tax_vat_number', 'TRN123456')
            ->assertJsonPath('data.profile.bank_account_number', '1234567890');

        $this->assertDatabaseHas('vendors', [
            'status' => VendorStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('vendor_profiles', [
            'email' => 'contractor-multipart@test.com',
            'business_name' => 'Contractor Co LLC',
            'trade_license_number' => 'TL-CONTRACTOR-1',
        ]);

        $vendor = Vendor::query()->whereHas('profile', fn ($q) => $q->where('email', 'contractor-multipart@test.com'))->first();
        $this->assertNotNull($vendor);
        $this->assertTrue($vendor->categories()->whereKey($category->id)->exists());
        $this->assertTrue($vendor->categories()->whereKey($sub->id)->exists());
        $this->assertTrue($vendor->services()->whereKey($service->id)->exists());
        $this->assertTrue($vendor->areas()->whereKey($area->id)->exists());

        $this->assertDatabaseHas('vendor_documents', [
            'vendor_id' => $vendor->id,
            'type' => 'trade_license',
        ]);
        $this->assertDatabaseHas('vendor_documents', [
            'vendor_id' => $vendor->id,
            'type' => 'vat_certificate',
        ]);
        $this->assertDatabaseHas('vendor_documents', [
            'vendor_id' => $vendor->id,
            'type' => 'bank_confirmation_letter',
        ]);
        $this->assertSame(3, VendorDocument::query()->where('vendor_id', $vendor->id)->count());

        $contractorUser = User::query()->where('email', 'contractor-multipart@test.com')->first();
        $this->assertNotNull($contractorUser);

        Notification::assertSentTo($admin, AdminNotification::class, function (AdminNotification $notification) use ($vendor) {
            $payload = $notification->toArray(User::query()->where('role', 'admin')->first());

            return ($payload['title'] ?? null) === 'New Contractor Registration'
                && ($payload['meta']['vendor_id'] ?? null) === $vendor->id
                && ($payload['meta']['action'] ?? null) === 'new_registration';
        });

        Notification::assertSentTo($contractorUser, VendorApplicationStatusNotification::class, function ($n) {
            $payload = $n->toArray($n->vendor->user);

            return ($payload['title'] ?? null) === 'Registration Under Review'
                && isset($payload['title_ar'], $payload['message_ar']);
        });
    }

    public function test_admin_can_request_missing_documents_after_registration(): void
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
