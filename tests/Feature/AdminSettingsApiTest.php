<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_user_cannot_access_admin_settings(): void
    {
        $this->getJson('/api/admin/settings')->assertStatus(401);
        $this->getJson('/api/admin/settings/system')->assertStatus(401);
        $this->putJson('/api/admin/settings/system', [])->assertStatus(401);
        $this->getJson('/api/admin/settings/theme')->assertStatus(401);
        $this->putJson('/api/admin/settings/theme', ['theme' => 'dark'])->assertStatus(401);
        $this->getJson('/api/admin/settings/language')->assertStatus(401);
        $this->putJson('/api/admin/settings/language', ['language' => 'en'])->assertStatus(401);
        $this->getJson('/api/admin/settings/payment')->assertStatus(401);
        $this->putJson('/api/admin/settings/payment', [
            'payment_gateway' => 'stripe',
            'api_key' => 'pk_test',
            'api_secret' => 'sk_test',
        ])->assertStatus(401);
        $this->getJson('/api/admin/settings/legal?type=privacy')->assertStatus(401);
        $this->postJson('/api/admin/settings/export-data', ['format' => 'json'])->assertStatus(401);
        $this->getJson('/api/admin/settings/debug-logs')->assertStatus(401);
    }

    /** @test */
    public function non_admin_user_cannot_access_admin_settings(): void
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $this->getJson('/api/admin/settings')->assertStatus(403);
        $this->getJson('/api/admin/settings/system')->assertStatus(403);
        $this->putJson('/api/admin/settings/system', ['push_notifications_enabled' => true])->assertStatus(403);
        $this->getJson('/api/admin/settings/theme')->assertStatus(403);
        $this->getJson('/api/admin/settings/language')->assertStatus(403);
        $this->getJson('/api/admin/settings/payment')->assertStatus(403);
        $this->getJson('/api/admin/settings/legal?type=privacy')->assertStatus(403);
        $this->postJson('/api/admin/settings/export-data', ['format' => 'json'])->assertStatus(403);
        $this->getJson('/api/admin/settings/debug-logs')->assertStatus(403);
    }

    /** @test */
    public function admin_can_get_all_settings(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'system' => [
                        'push_notifications_enabled',
                        'auto_assign_tasks',
                        'maintenance_mode',
                    ],
                    'app_config' => [
                        'theme',
                        'language',
                        'region',
                    ],
                    'payment' => [
                        'payment_gateway',
                        'api_key_set',
                        'api_secret_set',
                    ],
                    'legal' => [
                        'privacy_policy_url',
                        'terms_of_service_url',
                    ],
                ],
            ]);
    }

    /** @test */
    public function admin_can_get_and_update_system_settings(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $get = $this->getJson('/api/admin/settings/system');
        $get->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['push_notifications_enabled', 'auto_assign_tasks', 'maintenance_mode']]);

        $put = $this->putJson('/api/admin/settings/system', [
            'push_notifications_enabled' => false,
            'auto_assign_tasks' => true,
            'maintenance_mode' => true,
        ]);
        $put->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.push_notifications_enabled', false)
            ->assertJsonPath('data.auto_assign_tasks', true)
            ->assertJsonPath('data.maintenance_mode', true);

        $this->assertSame('0', Setting::get('push_notifications_enabled', '1'));
        $this->assertSame('1', Setting::get('auto_assign_tasks', '0'));
        $this->assertSame('1', Setting::get('maintenance_mode', '0'));
    }

    /** @test */
    public function admin_can_get_and_update_theme(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $get = $this->getJson('/api/admin/settings/theme');
        $get->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current', 'system')
            ->assertJsonFragment(['available' => ['system', 'light', 'dark']]);

        $put = $this->putJson('/api/admin/settings/theme', ['theme' => 'dark']);
        $put->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current', 'dark');

        $this->assertSame('dark', Setting::get('app_theme', 'system'));
    }

    /** @test */
    public function admin_can_get_and_update_language(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $get = $this->getJson('/api/admin/settings/language');
        $get->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_language', 'en')
            ->assertJsonStructure(['data' => ['available']]);

        $put = $this->putJson('/api/admin/settings/language', ['language' => 'ar', 'region' => 'SA']);
        $put->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_language', 'ar')
            ->assertJsonPath('data.current_region', 'SA');

        $this->assertSame('ar', Setting::get('app_language', 'en'));
        $this->assertSame('SA', Setting::get('app_region', ''));
    }

    /** @test */
    public function admin_can_get_and_update_payment_settings(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $get = $this->getJson('/api/admin/settings/payment');
        $get->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['payment_gateway', 'api_key_set', 'api_secret_set']]);

        $put = $this->putJson('/api/admin/settings/payment', [
            'payment_gateway' => 'stripe',
            'api_key' => 'pk_test_xxx',
            'api_secret' => 'sk_test_xxx',
        ]);
        $put->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_gateway', 'stripe')
            ->assertJsonPath('data.api_key_set', true)
            ->assertJsonPath('data.api_secret_set', true);

        $this->assertSame('stripe', Setting::get('payment_gateway', ''));
        $this->assertSame('pk_test_xxx', Setting::get('payment_api_key', ''));
    }

    /** @test */
    public function admin_can_get_legal_privacy_and_terms(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $privacy = $this->getJson('/api/admin/settings/legal?type=privacy');
        $privacy->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'privacy')
            ->assertJsonStructure(['data' => ['url', 'content']]);

        $terms = $this->getJson('/api/admin/settings/legal?type=terms');
        $terms->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'terms');
    }

    /** @test */
    public function legal_invalid_type_returns_400(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/settings/legal?type=invalid')->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function admin_can_request_export_data(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/settings/export-data', ['format' => 'json']);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Export requested.')
            ->assertJsonStructure(['data' => ['export_id', 'format', 'status']])
            ->assertJsonPath('data.format', 'json')
            ->assertJsonPath('data.status', 'pending');
    }

    /** @test */
    public function export_data_invalid_format_returns_400(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/settings/export-data', ['format' => 'xml'])->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function admin_can_get_debug_logs(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings/debug-logs?lines=50');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['lines', 'log']])
            ->assertJsonPath('data.lines', 50);
    }

    /** @test */
    public function update_theme_invalid_value_returns_422(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings/theme', ['theme' => 'invalid'])
            ->assertStatus(422);
    }

    /** @test */
    public function update_payment_invalid_gateway_returns_422(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings/payment', [
            'payment_gateway' => 'invalid',
            'api_key' => 'key',
            'api_secret' => 'secret',
        ])->assertStatus(422);
    }
}
