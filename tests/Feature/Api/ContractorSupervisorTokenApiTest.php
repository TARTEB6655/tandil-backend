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
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractorSupervisorTokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_contractor_login_token_works_on_supervisor_apis(): void
    {
        Setting::set(ContractorSignupOptionsService::SETTING_KEY, '1', 'boolean', 'contractor');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('supervisor', 'web');

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('a', ['admin'])->plainTextToken;

        $category = Category::query()->create(['name' => 'Cleaning', 'slug' => 'cleaning_tok', 'is_active' => true, 'contractor_signup_enabled' => true]);
        $service = Service::query()->create(['name' => 'Deep Clean', 'slug' => 'deep_clean_tok', 'is_active' => true, 'contractor_signup_enabled' => true, 'category_id' => $category->id]);
        $emirate = Emirate::query()->firstOrCreate(['slug' => 'dubai_tok'], ['name' => 'Dubai', 'is_active' => true, 'contractor_signup_enabled' => true]);
        $area = Area::query()->create(['name' => 'Token Zone', 'is_active' => true, 'contractor_signup_enabled' => true]);

        $register = $this->post('/api/contractor/auth/register', [
            'name' => 'Token Sup',
            'phone' => '+971501234567',
            'email' => 'token.supervisor@test.com',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'company_name' => 'Token Co',
            'trade_license_number' => 'TL-TOK',
            'trade_license_upload' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'),
            'trade_license_expiry_date' => '2027-12-31',
            'emirate' => 'Dubai',
            'city' => 'Dubai',
            'company_address' => 'Addr',
            'bank_name' => 'ENBD',
            'account_holder_name' => 'Token Co',
            'bank_account_number' => '123',
            'iban' => 'AE070331234567890123456',
            'bank_confirmation_letter' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            'main_service_categories' => [$category->id],
            'selected_services' => [$service->id],
            'emirates' => [$emirate->id],
            'service_coverage_areas' => [$area->id],
        ], ['Accept' => 'application/json'])->assertCreated();

        $regId = $register->json('data.registration_id');

        // Admin approve — next supervisor Bearer must still win (no leftover admin auth).
        $this->withToken($adminToken)
            ->postJson("/api/admin/supervisor-registrations/{$regId}/approve", [])
            ->assertOk();

        $login = $this->withoutToken()->postJson('/api/contractor/auth/login', [
            'email' => 'token.supervisor@test.com',
            'password' => 'secret12',
            'roles' => 'supervisor',
        ])->assertOk();

        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $pat = PersonalAccessToken::findToken($token);
        $this->assertNotNull($pat);
        $this->assertSame('token.supervisor@test.com', $pat->tokenable->email);

        $this->withToken($token)
            ->getJson('/api/supervisor/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->getJson('/api/supervisor/profile')
            ->assertOk();

        $this->withToken($adminToken)
            ->getJson('/api/supervisor/dashboard/summary')
            ->assertForbidden();
    }
}
