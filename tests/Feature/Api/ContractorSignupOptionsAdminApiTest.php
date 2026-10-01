<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Emirate;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Supervisor\ContractorSignupOptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractorSignupOptionsAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $this->token = $admin->createToken('t')->plainTextToken;
        Setting::set(ContractorSignupOptionsService::SETTING_KEY, '1', 'boolean', 'contractor');
    }

    public function test_screen_matches_contractor_signup_options_ui(): void
    {
        Category::query()->create([
            'name' => 'Home Maintenance',
            'slug' => 'home_maintenance',
            'is_active' => true,
            'contractor_signup_enabled' => true,
            'sort_order' => 1,
        ]);
        Category::query()->create([
            'name' => 'Cleaning',
            'slug' => 'cleaning',
            'is_active' => true,
            'contractor_signup_enabled' => false,
            'sort_order' => 2,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/admin/contractor-signup-options?tab=categories')
            ->assertOk()
            ->assertJsonPath('data.registration.is_open', true)
            ->assertJsonPath('data.registration.title', 'Contractor registration')
            ->assertJsonPath('data.tab', 'categories')
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.active', 1)
            ->assertJsonPath('data.items.0.name', 'Home Maintenance')
            ->assertJsonPath('data.items.0.slug', 'home_maintenance')
            ->assertJsonPath('data.items.0.is_active', true)
            ->assertJsonPath('data.items.1.is_active', false);
    }

    public function test_admin_can_crud_toggle_across_tabs(): void
    {
        $cat = $this->withToken($this->token)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'categories',
                'name' => 'Pest Control',
                'slug' => 'pest_control',
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.name', 'Pest Control')
            ->assertJsonPath('data.item.is_active', true)
            ->json('data.item');

        $this->withToken($this->token)
            ->postJson("/api/admin/contractor-signup-options/{$cat['id']}/toggle", [
                'tab' => 'categories',
            ])
            ->assertOk()
            ->assertJsonPath('data.item.is_active', false);

        $sub = $this->withToken($this->token)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'subcategories',
                'name' => 'AC Services',
                'parent_id' => $cat['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.parent_id', $cat['id'])
            ->json('data.item');

        $this->withToken($this->token)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'services',
                'name' => 'AC Installation',
                'slug' => 'ac_installation',
                'category_id' => $sub['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.slug', 'ac_installation')
            ->assertJsonPath('data.item.parent_name', 'AC Services');

        $emirate = $this->withToken($this->token)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'emirates',
                'name' => 'Dubai',
                'slug' => 'dubai',
            ])
            ->assertCreated()
            ->json('data.item');

        $this->withToken($this->token)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'cities',
                'name' => 'Marina',
                'emirate_id' => $emirate['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.parent_name', 'Dubai');

        $zone = $this->withToken($this->token)
            ->postJson('/api/admin/contractor-signup-options', [
                'tab' => 'coverage',
                'name' => 'Downtown Zone',
            ])
            ->assertCreated()
            ->assertJsonPath('data.item.slug', 'downtown_zone')
            ->json('data.item');

        $this->withToken($this->token)
            ->deleteJson("/api/admin/contractor-signup-options/{$zone['id']}?tab=coverage")
            ->assertOk()
            ->assertJsonPath('data.item.deleted', true);

        $this->assertDatabaseMissing('areas', ['id' => $zone['id']]);
    }

    public function test_closing_registration_blocks_contractor_signup(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/admin/contractor-signup-options/settings', ['is_open' => false])
            ->assertOk()
            ->assertJsonPath('data.registration.is_open', false);

        $this->post('/api/contractor/auth/register', [
            'name' => 'Blocked',
            'phone' => '+971500009999',
            'email' => 'blocked-contractor@test.com',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'company_name' => 'Blocked Co',
            'trade_license_number' => 'TL-1',
            'trade_license_upload' => \Illuminate\Http\UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'),
            'trade_license_expiry_date' => '2027-12-31',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'company_address' => 'Addr',
            'bank_name' => 'ENBD',
            'account_holder_name' => 'Blocked Co',
            'bank_account_number' => '123',
            'iban' => 'AE070331234567890123456',
            'bank_confirmation_letter' => \Illuminate\Http\UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            'main_service_categories' => [1],
            'selected_services' => [1],
            'emirates' => [1],
            'service_coverage_areas' => [1],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_disabled_option_hidden_from_public_catalog(): void
    {
        $cat = Category::query()->create([
            'name' => 'Hidden Cat',
            'slug' => 'hidden_cat',
            'is_active' => true,
            'contractor_signup_enabled' => false,
        ]);
        Emirate::query()->firstOrCreate(
            ['slug' => 'sharjah_signup_test'],
            ['name' => 'Sharjah Signup Test', 'is_active' => true, 'contractor_signup_enabled' => true]
        );
        Area::query()->create(['name' => 'Residential North', 'is_active' => true, 'contractor_signup_enabled' => true]);
        Service::query()->create([
            'name' => 'Wiring Check',
            'slug' => 'wiring_check',
            'is_active' => true,
            'contractor_signup_enabled' => true,
            'category_id' => $cat->id,
        ]);

        $schema = app(\App\Services\Vendor\ContractorRegistrationSchemaService::class)->optionCatalog();
        $catIds = collect($schema['main_service_categories'])->pluck('id')->all();
        $this->assertNotContains($cat->id, $catIds);
        $this->assertNotEmpty($schema['emirates']);
        $this->assertNotEmpty($schema['service_coverage_areas']);
    }

    public function test_contractor_registration_options_endpoint_returns_signup_enabled_catalog(): void
    {
        $category = Category::query()->create([
            'name' => 'Cleaning Public',
            'slug' => 'cleaning_public',
            'is_active' => true,
            'contractor_signup_enabled' => true,
        ]);
        $sub = Category::query()->create([
            'name' => 'Deep Clean Sub',
            'slug' => 'deep_clean_sub',
            'is_active' => true,
            'parent_id' => $category->id,
            'contractor_signup_enabled' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Sofa Clean',
            'slug' => 'sofa_clean',
            'is_active' => true,
            'contractor_signup_enabled' => true,
            'category_id' => $category->id,
        ]);
        $emirate = Emirate::query()->firstOrCreate(
            ['slug' => 'dubai_public'],
            ['name' => 'Dubai Public', 'is_active' => true, 'contractor_signup_enabled' => true]
        );
        $area = Area::query()->create([
            'name' => 'Public Zone',
            'is_active' => true,
            'contractor_signup_enabled' => true,
        ]);

        $response = $this->getJson('/api/contractor/auth/registration-options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'options' => [
                        'main_service_categories',
                        'service_subcategories',
                        'selected_services',
                        'emirates',
                        'cities',
                        'service_coverage_areas',
                    ],
                ],
            ]);

        $options = $response->json('data.options');
        $this->assertContains($category->id, collect($options['main_service_categories'])->pluck('id')->all());
        $this->assertContains($sub->id, collect($options['service_subcategories'])->pluck('id')->all());
        $this->assertContains($service->id, collect($options['selected_services'])->pluck('id')->all());
        $this->assertContains($emirate->id, collect($options['emirates'])->pluck('id')->all());
        $this->assertContains($area->id, collect($options['service_coverage_areas'])->pluck('id')->all());

        // Legacy vendor path still works (same handler).
        $this->getJson('/api/vendor/auth/registration-options')->assertOk();
    }
}
