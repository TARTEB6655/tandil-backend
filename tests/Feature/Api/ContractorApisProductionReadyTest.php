<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Emirate;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SupervisorRegistration;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\SupervisorRegistrationStatusNotification;
use App\Services\Supervisor\ContractorSignupOptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Production-ready walkthrough: every contractor / signup-options / admin review endpoint.
 */
class ContractorApisProductionReadyTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('supervisor', 'web');

        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-contractor-e2e@test.com']);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

        Setting::set(ContractorSignupOptionsService::SETTING_KEY, '1', 'boolean', 'contractor');
    }

    public function test_full_contractor_api_surface_one_by_one(): void
    {
        Notification::fake();

        // ── 0. Auth guards ──────────────────────────────────────────────
        $this->getJson('/api/admin/contractor-signup-options')->assertUnauthorized();
        $this->getJson('/api/admin/supervisor-registrations')->assertUnauthorized();

        // ── 1. Signup options screen — every tab ────────────────────────
        foreach (['categories', 'subcategories', 'services', 'emirates', 'cities', 'coverage'] as $tab) {
            $this->withToken($this->adminToken)
                ->getJson("/api/admin/contractor-signup-options?tab={$tab}")
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.tab', $tab)
                ->assertJsonPath('data.registration.is_open', true)
                ->assertJsonStructure([
                    'data' => [
                        'registration' => ['is_open', 'title', 'subtitle'],
                        'tabs',
                        'summary' => ['total', 'active', 'label'],
                        'items',
                    ],
                ]);
        }

        $tabs = $this->withToken($this->adminToken)
            ->getJson('/api/admin/contractor-signup-options?tab=categories')
            ->json('data.tabs');
        $this->assertCount(6, $tabs);
        $this->assertSame(
            ['categories', 'subcategories', 'services', 'emirates', 'cities', 'coverage'],
            collect($tabs)->pluck('key')->all()
        );

        // ── 2. Master toggle OFF then ON ────────────────────────────────
        $this->withToken($this->adminToken)
            ->putJson('/api/admin/contractor-signup-options/settings', ['is_open' => false])
            ->assertOk()
            ->assertJsonPath('data.registration.is_open', false)
            ->assertJsonPath('data.screen.registration.is_open', false);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options/settings', ['is_open' => true])
            ->assertOk()
            ->assertJsonPath('data.registration.is_open', true);

        // ── 3. Categories CRUD + toggle ─────────────────────────────────
        $category = $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'categories',
                'name' => 'Home Maintenance',
                'slug' => 'home_maintenance',
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.name', 'Home Maintenance')
            ->assertJsonPath('data.item.slug', 'home_maintenance')
            ->assertJsonPath('data.item.is_active', true)
            ->assertJsonPath('data.item.actions.toggle', true)
            ->assertJsonPath('data.screen.summary.active', 1)
            ->json('data.item');

        $this->withToken($this->adminToken)
            ->putJson("/api/admin/contractor-signup-options/{$category['id']}", [
                'tab' => 'categories',
                'name' => 'Home Maintenance Plus',
            ])
            ->assertOk()
            ->assertJsonPath('data.item.name', 'Home Maintenance Plus');

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/contractor-signup-options/{$category['id']}/toggle", [
                'tab' => 'categories',
            ])
            ->assertOk()
            ->assertJsonPath('data.item.is_active', false);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/contractor-signup-options/{$category['id']}/toggle", [
                'tab' => 'categories',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.item.is_active', true);

        // ── 4. Subcategories ────────────────────────────────────────────
        $sub = $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'subcategories',
                'name' => 'AC Services',
                'slug' => 'ac_services',
                'parent_id' => $category['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.parent_id', $category['id'])
            ->assertJsonPath('data.item.parent_name', 'Home Maintenance Plus')
            ->json('data.item');

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'subcategories',
                'name' => 'Missing Parent',
            ])
            ->assertStatus(422);

        // ── 5. Services ─────────────────────────────────────────────────
        $service = $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'services',
                'name' => 'AC Installation',
                'slug' => 'ac_installation',
                'category_id' => $sub['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.slug', 'ac_installation')
            ->assertJsonPath('data.item.parent_name', 'AC Services')
            ->json('data.item');

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/contractor-signup-options/{$service['id']}/toggle", [
                'tab' => 'services',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.item.is_active', false);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/contractor-signup-options/{$service['id']}/toggle", [
                'tab' => 'services',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.item.is_active', true);

        // ── 6. Emirates / Cities / Coverage ─────────────────────────────
        $emirate = $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'emirates',
                'name' => 'Abu Dhabi',
                'slug' => 'abu_dhabi',
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.slug', 'abu_dhabi')
            ->json('data.item');

        $city = $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'cities',
                'name' => 'Al Reem',
                'emirate_id' => $emirate['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.parent_name', 'Abu Dhabi')
            ->json('data.item');

        $zone = $this->withToken($this->adminToken)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'coverage',
                'name' => 'Industrial Zone',
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.slug', 'industrial_zone')
            ->json('data.item');

        // Disabled option must leave public catalog
        $this->withToken($this->adminToken)
            ->postJson("/api/admin/contractor-signup-options/{$city['id']}/toggle", [
                'tab' => 'cities',
                'is_active' => false,
            ])
            ->assertOk();

        $catalog = app(\App\Services\Vendor\ContractorRegistrationSchemaService::class)->optionCatalog();
        $this->assertNotContains($city['id'], collect($catalog['cities'])->pluck('id')->all());
        $this->assertContains($emirate['id'], collect($catalog['emirates'])->pluck('id')->all());
        $this->assertContains($zone['id'], collect($catalog['service_coverage_areas'])->pluck('id')->all());
        $this->assertContains($category['id'], collect($catalog['main_service_categories'])->pluck('id')->all());
        $this->assertContains($service['id'], collect($catalog['selected_services'])->pluck('id')->all());

        // Re-enable city for register
        $this->withToken($this->adminToken)
            ->postJson("/api/admin/contractor-signup-options/{$city['id']}/toggle", [
                'tab' => 'cities',
                'is_active' => true,
            ])
            ->assertOk();

        // ── 7. Register blocked when closed ─────────────────────────────
        $this->withToken($this->adminToken)
            ->putJson('/api/admin/contractor-signup-options/settings', ['is_open' => false])
            ->assertOk();

        $this->postContractorRegister('closed@test.com', $category['id'], $service['id'], $emirate['id'], $zone['id'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['registration']);

        $this->withToken($this->adminToken)
            ->putJson('/api/admin/contractor-signup-options/settings', ['is_open' => true])
            ->assertOk();

        // ── 8. Public register → pending supervisor (not vendor) ────────
        $register = $this->postContractorRegister(
            'ahmed.contractor@example.com',
            $category['id'],
            $service['id'],
            $emirate['id'],
            $zone['id'],
            [
                'name' => 'Ahmed Al Mansoori',
                'company_name' => 'Al Barsha Maintenance LLC',
                'phone' => '+971501112233',
                'trade_license_number' => 'TL-NG_1-2024',
            ]
        )->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Pending approval')
            ->assertJsonPath('data.company_name', 'Al Barsha Maintenance LLC')
            ->assertJsonMissingPath('data.vendor_id');

        $registrationId = (int) $register->json('data.registration_id');
        $supervisorId = (int) $register->json('data.supervisor_id');
        $this->assertGreaterThan(0, $registrationId);
        $this->assertDatabaseMissing('vendors', ['user_id' => $supervisorId]);

        $contractor = User::query()->findOrFail($supervisorId);
        $this->assertSame('supervisor', $contractor->role);
        $this->assertSame('pending', $contractor->status);

        Notification::assertSentTo($this->admin, AdminNotification::class, function (AdminNotification $n) {
            $p = $n->toArray($this->admin);

            return ($p['title'] ?? null) === 'New Contractor Registration'
                && ($p['meta']['entity'] ?? null) === 'supervisor_registration';
        });
        Notification::assertSentTo($contractor, SupervisorRegistrationStatusNotification::class);

        // Pending login should fail (account not active)
        $this->postJson('/api/contractor/auth/login', [
            'email' => 'ahmed.contractor@example.com',
            'password' => 'secret12',
            'roles' => 'supervisor',
        ])->assertStatus(403);

        // ── 9. Admin list / recent / show ───────────────────────────────
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/supervisor-registrations?status=pending')
            ->assertOk()
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.filters.pending', 1)
            ->assertJsonPath('data.items.0.registration_id', $registrationId)
            ->assertJsonPath('data.items.0.company_name', 'Al Barsha Maintenance LLC')
            ->assertJsonPath('data.items.0.contact_line', 'Ahmed Al Mansoori · ahmed.contractor@example.com')
            ->assertJsonPath('data.items.0.status_label', 'Pending approval')
            ->assertJsonPath('data.items.0.status_badge', 'PENDING')
            ->assertJsonPath('data.items.0.actions.approve', true)
            ->assertJsonPath('data.items.0.actions.cancel', true)
            ->assertJsonPath('data.settings_link.endpoint', '/api/admin/contractor-signup-options');

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/supervisor-registrations/recent?limit=5')
            ->assertOk()
            ->assertJsonPath('data.total_pending', 1)
            ->assertJsonPath('data.items.0.registration_id', $registrationId)
            ->assertJsonPath('data.items.0.status_badge', 'PENDING')
            ->assertJsonPath('data.view_all.endpoint', '/api/admin/supervisor-registrations');

        $this->withToken($this->adminToken)
            ->getJson("/api/admin/supervisor-registrations/{$registrationId}")
            ->assertOk()
            ->assertJsonPath('data.personal.full_name', 'Ahmed Al Mansoori')
            ->assertJsonPath('data.personal.email', 'ahmed.contractor@example.com')
            ->assertJsonPath('data.personal.phone', '+971501112233')
            ->assertJsonPath('data.company.trade_license', 'TL-NG_1-2024')
            ->assertJsonPath('data.company.trade_license_file.name', 'trade-license.pdf')
            ->assertJsonPath('data.actions.approve', true)
            ->assertJsonPath('data.actions.reject', true)
            ->assertJsonPath('data.actions.delete', true);

        // ── 10. Request documents → still pending UI ───────────────────
        $this->withToken($this->adminToken)
            ->postJson("/api/admin/supervisor-registrations/{$registrationId}/request-documents", [
                'message' => 'Please upload VAT certificate.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.registration_status', 'documents_requested')
            ->assertJsonPath('data.status_label', 'Pending approval');

        // ── 11. Approve → Active ────────────────────────────────────────
        $this->withToken($this->adminToken)
            ->postJson("/api/admin/supervisor-registrations/{$registrationId}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.registration_status', 'approved')
            ->assertJsonPath('data.status_label', 'Active');

        $this->assertSame('active', $contractor->fresh()->status);

        $login = $this->postJson('/api/contractor/auth/login', [
            'email' => 'ahmed.contractor@example.com',
            'password' => 'secret12',
            'roles' => 'supervisor',
        ])->assertOk();
        $this->assertNotEmpty($login->json('data.token') ?? $login->json('data.access_token'));

        // ── 12. Suspend / Activate ──────────────────────────────────────
        $this->withToken($this->adminToken)
            ->postJson("/api/admin/supervisor-registrations/{$registrationId}/account-status", [
                'action' => 'suspend',
                'notes' => 'Temporary hold',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.status_label', 'Suspended');

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/supervisor-registrations?status=suspended')
            ->assertOk()
            ->assertJsonPath('data.filters.suspended', 1)
            ->assertJsonPath('data.items.0.registration_id', $registrationId);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/supervisor-registrations/{$registrationId}/account-status", [
                'action' => 'activate',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        // ── 13. Reject flow on second contractor ────────────────────────
        $reg2 = $this->postContractorRegister(
            'reject.me@example.com',
            $category['id'],
            $service['id'],
            $emirate['id'],
            $zone['id'],
            ['company_name' => 'Reject Co', 'phone' => '+971509998877']
        )->assertCreated()->json('data');

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/supervisor-registrations/{$reg2['registration_id']}/reject", [
                'reason' => 'Invalid trade license',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_label', 'Rejected');

        $this->assertSame('inactive', User::query()->find($reg2['supervisor_id'])->status);

        // ── 14. Delete contractor ───────────────────────────────────────
        $reg3 = $this->postContractorRegister(
            'delete.me@example.com',
            $category['id'],
            $service['id'],
            $emirate['id'],
            $zone['id'],
            ['company_name' => 'Delete Co', 'phone' => '+971507776655']
        )->assertCreated()->json('data');

        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/supervisor-registrations/{$reg3['registration_id']}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('supervisor_registrations', ['id' => $reg3['registration_id']]);
        $this->assertDatabaseMissing('users', ['id' => $reg3['supervisor_id']]);

        // ── 15. Delete signup option (coverage) ─────────────────────────
        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/contractor-signup-options/{$zone['id']}?tab=coverage")
            ->assertOk()
            ->assertJsonPath('data.item.deleted', true);
        $this->assertDatabaseMissing('areas', ['id' => $zone['id']]);

        // Cannot delete category that still has subcategory
        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/contractor-signup-options/{$category['id']}?tab=categories")
            ->assertStatus(422);

        // Search filter on management list
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/supervisor-registrations?search=Al%20Barsha')
            ->assertOk()
            ->assertJsonPath('data.items.0.company_name', 'Al Barsha Maintenance LLC');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postContractorRegister(
        string $email,
        int $categoryId,
        int $serviceId,
        int $emirateId,
        int $areaId,
        array $overrides = []
    ) {
        $payload = array_merge([
            'name' => 'Contractor User',
            'phone' => '+97150'.random_int(1000000, 9999999),
            'email' => $email,
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'company_name' => 'Contractor Co LLC',
            'trade_license_number' => 'TL-'.random_int(1000, 9999),
            'trade_license_upload' => UploadedFile::fake()->create('trade-license.pdf', 80, 'application/pdf'),
            'trade_license_expiry_date' => '2027-12-31',
            'trn' => '100123456700003',
            'emirate' => 'Abu Dhabi',
            'city' => 'Al Reem',
            'company_address' => 'Business Bay, Dubai, UAE',
            'bank_name' => 'Emirates NBD',
            'account_holder_name' => 'Contractor Co LLC',
            'bank_account_number' => '1234567890',
            'iban' => 'AE070331234567890123456',
            'bank_confirmation_letter' => UploadedFile::fake()->create('bank.pdf', 60, 'application/pdf'),
            'main_service_categories' => [$categoryId],
            'selected_services' => [$serviceId],
            'emirates' => [$emirateId],
            'service_coverage_areas' => [$areaId],
        ], $overrides);

        return $this->post('/api/contractor/auth/register', $payload, ['Accept' => 'application/json']);
    }
}
