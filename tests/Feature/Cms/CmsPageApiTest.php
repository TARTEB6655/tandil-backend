<?php

namespace Tests\Feature\Cms;

use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CmsPageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_public_lists_all_managed_cms_pages(): void
    {
        $this->getJson('/api/public/cms/pages')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonFragment(['slug' => 'privacy-policy'])
            ->assertJsonFragment(['slug' => 'terms-and-conditions'])
            ->assertJsonFragment(['slug' => 'contact-us']);
    }

    public function test_public_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/public/cms/pages/unknown-page')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_list_and_show_cms_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/cms/pages')
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.suggested_locales', ['en', 'ar', 'ur']);

        $this->withToken($token)
            ->getJson('/api/admin/cms/pages/terms-and-conditions')
            ->assertOk()
            ->assertJsonPath('data.slug', 'terms-and-conditions')
            ->assertJsonStructure([
                'data' => [
                    'slug',
                    'label',
                    'translations',
                    'is_active',
                    'suggested_locales',
                ],
            ]);
    }

    public function test_admin_can_update_terms_and_conditions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/terms-and-conditions', [
                'translations' => [
                    'en' => ['title' => 'Terms', 'body' => '<p>English terms</p>'],
                    'ur' => ['title' => 'شرائط', 'body' => '<p>Urdu terms</p>'],
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.translations.en.body', '<p>English terms</p>');

        $this->getJson('/api/public/cms/pages/terms-and-conditions')
            ->assertOk()
            ->assertJsonPath('data.translations.ur.body', '<p>Urdu terms</p>');
    }

    public function test_legal_settings_alias_get_and_put_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/settings/legal?type=privacy')
            ->assertOk()
            ->assertJsonPath('data.slug', 'privacy-policy')
            ->assertJsonStructure(['data' => ['translations', 'type', 'url']]);

        $this->withToken($token)
            ->putJson('/api/admin/settings/legal?type=terms', [
                'translations' => [
                    'en' => ['title' => 'Terms via alias', 'body' => '<p>Alias update</p>'],
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'terms-and-conditions')
            ->assertJsonPath('data.translations.en.title', 'Terms via alias');
    }

    public function test_non_admin_cannot_update_cms_pages(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $token = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/privacy-policy', [
                'translations' => ['en' => ['title' => 'Hack', 'body' => 'x']],
            ])
            ->assertForbidden();
    }

    public function test_inactive_page_is_hidden_from_public_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/privacy-policy', [
                'translations' => [
                    'en' => ['title' => 'Hidden', 'body' => '<p>Hidden</p>'],
                ],
                'is_active' => false,
            ])
            ->assertOk();

        $this->getJson('/api/public/cms/pages/privacy-policy')
            ->assertNotFound();
    }

    public function test_help_center_uses_cms_contact_details(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $adminToken = $admin->createToken('test', ['admin'])->plainTextToken;
        $clientToken = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($adminToken)
            ->putJson('/api/admin/cms/pages/contact-us', [
                'translations' => [
                    'en' => ['title' => 'Contact', 'body' => '<p>Contact body</p>'],
                ],
                'contact_details' => [
                    'phone' => '+971599988877',
                    'whatsapp' => '+971599988877',
                    'email' => 'cms-contact@tandil.com',
                    'working_hours' => ['en' => 'Daily 8am-8pm'],
                    'service_areas' => ['en' => 'All UAE'],
                ],
                'is_active' => true,
            ])
            ->assertOk();

        $this->withToken($clientToken)
            ->getJson('/api/support/help-center')
            ->assertOk()
            ->assertJsonPath('data.contact_info.phone', '+971599988877')
            ->assertJsonPath('data.contact_info.whatsapp', '+971599988877')
            ->assertJsonPath('data.contact_info.email', 'cms-contact@tandil.com')
            ->assertJsonPath('data.contact_info.support_hours', 'Daily 8am-8pm')
            ->assertJsonPath('data.contact_info.service_areas', 'All UAE');
    }

    public function test_admin_web_can_save_cms_page_from_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.cms-pages.update', 'privacy-policy'), [
                'translations' => [
                    'en' => ['title' => 'Web Save', 'body' => '<p>Saved from admin form</p>'],
                ],
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.cms-pages.edit', 'privacy-policy'));

        $this->getJson('/api/public/cms/pages/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.translations.en.title', 'Web Save');
    }

    public function test_public_can_fetch_cms_page_with_all_translations(): void
    {
        $this->getJson('/api/public/cms/pages/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.slug', 'privacy-policy')
            ->assertJsonStructure([
                'data' => [
                    'slug',
                    'label',
                    'translations',
                ],
            ]);
    }

    public function test_admin_can_update_privacy_policy_in_multiple_languages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/privacy-policy', [
                'translations' => [
                    'en' => ['title' => 'Privacy Policy', 'body' => '<p>English privacy</p>'],
                    'ar' => ['title' => 'سياسة الخصوصية', 'body' => '<p>Arabic privacy</p>'],
                    'ur' => ['title' => 'رازداری کی پالیسی', 'body' => '<p>Urdu privacy</p>'],
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.translations.en.body', '<p>English privacy</p>')
            ->assertJsonPath('data.translations.ar.title', 'سياسة الخصوصية');

        $this->getJson('/api/public/cms/pages/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.translations.ar.body', '<p>Arabic privacy</p>');
    }

    public function test_admin_can_update_contact_us_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/contact-us', [
                'translations' => [
                    'en' => ['title' => 'Contact Us', 'body' => '<p>Get in touch</p>'],
                ],
                'contact_details' => [
                    'phone' => '+971501112233',
                    'whatsapp' => '+971501112233',
                    'email' => 'hello@tandil.com',
                    'working_hours' => ['en' => '9am - 6pm'],
                    'service_areas' => ['en' => 'Dubai, Sharjah'],
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.contact_details.phone', '+971501112233')
            ->assertJsonPath('data.contact_details.whatsapp', '+971501112233');

        $this->getJson('/api/public/cms/pages/contact-us')
            ->assertOk()
            ->assertJsonPath('data.contact_details.email', 'hello@tandil.com');
    }

    public function test_admin_web_can_open_cms_pages_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.cms-pages.index'))
            ->assertOk()
            ->assertSee('CMS Pages')
            ->assertSee('Privacy Policy')
            ->assertSee('Terms & Conditions')
            ->assertSee('Contact Us');
    }

    public function test_public_terms_and_contact_routes_render_saved_content(): void
    {
        CmsPage::query()->create([
            'slug' => CmsPage::SLUG_TERMS,
            'label' => 'Terms & Conditions',
            'translations' => [
                'en' => ['title' => 'Terms & Conditions', 'body' => '<p>Custom terms body</p>'],
            ],
            'is_active' => true,
        ]);

        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertSee('Custom terms body', false);
    }
}
