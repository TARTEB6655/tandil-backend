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

    public function test_public_client_and_vendor_endpoints_return_app_payload(): void
    {
        $this->getJson('/api/client/privacy-policy?lang=en')
            ->assertOk()
            ->assertJsonPath('data.audience', 'client')
            ->assertJsonPath('data.slug', 'privacy-policy')
            ->assertJsonStructure(['data' => ['title', 'subtitle', 'body']]);

        $this->getJson('/api/vendor/contact-us?lang=en')
            ->assertOk()
            ->assertJsonPath('data.audience', 'vendor')
            ->assertJsonPath('data.slug', 'contact-us')
            ->assertJsonStructure(['data' => ['hero', 'company', 'reach_us']]);

        $this->getJson('/api/client/terms-and-conditions?lang=en')
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('data.intro', fn ($intro) => is_string($intro) && str_contains($intro, 'Welcome to Tandil'))->etc())
            ->assertJsonPath('data.sections', []);
    }

    public function test_public_returns_404_for_inactive_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->put('/api/admin/client/privacy-policy', [
                'page_title' => 'Hidden',
                'content_body' => '<p>Hidden</p>',
                'is_active' => '0',
            ])
            ->assertOk();

        $this->getJson('/api/client/privacy-policy')->assertNotFound();
    }

    public function test_admin_client_pages_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/client/pages')
            ->assertOk()
            ->assertJsonPath('data.audience', 'client')
            ->assertJsonCount(3, 'data.items')
            ->assertJsonFragment(['page_key' => 'contact_us']);
    }

    public function test_admin_vendor_contact_form_get_and_put(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/vendor/contact-us')
            ->assertOk()
            ->assertJsonPath('data.audience', 'vendor')
            ->assertJsonStructure([
                'data' => [
                    'page_title',
                    'company_name',
                    'website_url',
                    'website_label',
                    'email',
                    'phone',
                    'whatsapp_display',
                    'whatsapp_dial_number',
                    'country',
                    'hero_title',
                    'hero_description',
                    'support_note',
                ],
            ]);

        $this->withToken($token)
            ->put('/api/admin/vendor/contact-us', [
                'page_title' => 'Contact Us',
                'company_name' => 'Tandil',
                'website_url' => 'https://tandil.ae',
                'website_label' => 'tandil.ae',
                'email' => 'vendor@tandil.com',
                'phone' => '+971569206959',
                'whatsapp_display' => '+971 569206959',
                'whatsapp_dial_number' => '+971569206959',
                'country' => 'United Arab Emirates',
                'hero_title' => 'Get in touch with Tandil',
                'hero_description' => 'Vendor hero text',
                'support_note' => 'Responds within 24-48 hours',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'vendor@tandil.com');

        $this->getJson('/api/vendor/contact-us?lang=en')
            ->assertOk()
            ->assertJsonPath('data.hero.description', 'Vendor hero text')
            ->assertJsonFragment(['value' => 'vendor@tandil.com']);
    }

    public function test_admin_client_privacy_and_terms_form_put(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->put('/api/admin/client/privacy-policy', [
                'page_title' => 'Privacy Policy',
                'content_body' => '<p>Client privacy from form</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.content_body', '<p>Client privacy from form</p>');

        $this->getJson('/api/client/privacy-policy?lang=en')
            ->assertOk()
            ->assertJsonPath('data.body', '<p>Client privacy from form</p>');

        $this->withToken($token)
            ->put('/api/admin/client/terms-and-conditions', [
                'page_title' => 'Terms & Conditions',
                'content_body' => '<p>Client terms from form</p>',
            ])
            ->assertOk();

        $this->getJson('/api/client/terms-and-conditions?lang=en')
            ->assertOk()
            ->assertJsonPath('data.intro', '<p>Client terms from form</p>');
    }

    public function test_client_and_vendor_content_is_stored_separately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->put('/api/admin/client/privacy-policy', [
                'page_title' => 'Client Privacy',
                'content_body' => '<p>Client only</p>',
            ]);

        $this->withToken($token)
            ->put('/api/admin/vendor/privacy-policy', [
                'page_title' => 'Vendor Privacy',
                'content_body' => '<p>Vendor only</p>',
            ]);

        $this->getJson('/api/client/privacy-policy?lang=en')
            ->assertJsonPath('data.body', '<p>Client only</p>');

        $this->getJson('/api/vendor/privacy-policy?lang=en')
            ->assertJsonPath('data.body', '<p>Vendor only</p>');
    }

    public function test_non_admin_cannot_update_legal_content(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $token = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($token)
            ->put('/api/admin/client/privacy-policy', [
                'page_title' => 'Hack',
                'content_body' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_help_center_uses_client_contact_details(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $adminToken = $admin->createToken('test', ['admin'])->plainTextToken;
        $clientToken = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($adminToken)
            ->put('/api/admin/client/contact-us', [
                'page_title' => 'Contact',
                'company_name' => 'Tandil',
                'email' => 'cms-contact@tandil.com',
                'phone' => '+971599988877',
                'whatsapp_dial_number' => '+971599988877',
                'whatsapp_display' => '+971 599988877',
                'website_url' => 'https://tandil.ae',
                'website_label' => 'tandil.ae',
                'country' => 'All UAE',
                'hero_title' => 'Get in touch',
                'hero_description' => 'Client hero',
                'support_note' => '24h response',
            ]);

        $this->withToken($clientToken)
            ->getJson('/api/support/help-center')
            ->assertOk()
            ->assertJsonPath('data.contact_info.phone', '+971599988877')
            ->assertJsonPath('data.contact_info.email', 'cms-contact@tandil.com');
    }

    public function test_admin_web_and_public_web_routes_still_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.cms-pages.index'))
            ->assertOk()
            ->assertSee('CMS Pages');

        $page = CmsPage::query()->where('slug', CmsPage::SLUG_TERMS)->firstOrFail();
        $translations = $page->translations ?? [];
        $translations['client']['en'] = ['title' => 'Terms', 'intro' => '<p>Web terms</p>'];
        $translations['vendor']['en'] = ['title' => 'Terms', 'intro' => '<p>Vendor web</p>'];
        $page->update(['translations' => $translations, 'is_active' => true]);

        $this->get('/terms-and-conditions?audience=client')
            ->assertOk()
            ->assertSee('Web terms', false);
    }

    public function test_old_cms_api_routes_are_removed(): void
    {
        $this->getJson('/api/public/cms/pages')->assertNotFound();
        $this->getJson('/api/admin/cms/legal-content?audience=client&page=contact_us')->assertNotFound();
        $this->getJson('/api/admin/cms/pages')->assertNotFound();
    }

    public function test_ensure_defaults_backfills_empty_cms_records(): void
    {
        CmsPage::query()->create([
            'slug' => CmsPage::SLUG_PRIVACY,
            'label' => 'Privacy Policy',
            'translations' => [
                CmsPage::AUDIENCE_CLIENT => [
                    'en' => ['title' => 'Privacy Policy', 'subtitle' => '', 'body' => ''],
                ],
            ],
            'is_active' => true,
        ]);

        CmsPage::query()->create([
            'slug' => CmsPage::SLUG_CONTACT,
            'label' => 'Contact Us',
            'translations' => [
                CmsPage::AUDIENCE_CLIENT => [
                    'en' => [
                        'title' => 'Contact Us',
                        'subtitle' => 'We are here to help you',
                        'hero_title' => 'Get in touch with Tandil',
                    ],
                ],
            ],
            'contact_details' => [
                CmsPage::AUDIENCE_CLIENT => [
                    'company_name' => 'Tandil',
                    'location' => ['en' => 'United Arab Emirates'],
                ],
            ],
            'is_active' => true,
        ]);

        $this->getJson('/api/client/privacy-policy?lang=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Privacy Policy')
            ->assertJson(fn ($json) => $json->where('data.body', fn ($body) => is_string($body) && strlen($body) > 20)->etc());

        $this->getJson('/api/client/contact-us?lang=en')
            ->assertOk()
            ->assertJsonPath('data.company.name', 'Tandil')
            ->assertJsonPath('data.hero.description', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_admin_form_update_does_not_wipe_existing_content_with_empty_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->getJson('/api/client/privacy-policy?lang=en')->assertOk();

        $this->withToken($token)
            ->put('/api/admin/client/privacy-policy', [
                'page_title' => 'Privacy Policy',
                'content_body' => '<p>Saved privacy text</p>',
            ])
            ->assertOk();

        $this->withToken($token)
            ->put('/api/admin/client/privacy-policy', [
                'page_title' => 'Privacy Policy',
                'content_body' => '',
            ])
            ->assertOk();

        $this->getJson('/api/client/privacy-policy?lang=en')
            ->assertOk()
            ->assertJsonPath('data.body', '<p>Saved privacy text</p>');
    }

    public function test_terms_public_api_uses_admin_intro_and_hides_default_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->getJson('/api/client/terms-and-conditions?lang=en')->assertOk();

        $page = CmsPage::query()->where('slug', CmsPage::SLUG_TERMS)->firstOrFail();
        $translations = $page->translations ?? [];
        $translations['client']['en'] = [
            'title' => 'Terms & Conditions',
            'effective_date' => 'July 9, 2026',
            'intro' => '<p>Custom terms from admin</p>',
            'sections' => [
                ['title' => 'About Tandil', 'body' => 'Default seeded section body'],
            ],
        ];
        $page->update(['translations' => $translations]);

        $this->getJson('/api/client/terms-and-conditions?lang=en')
            ->assertOk()
            ->assertJsonPath('data.intro', '<p>Custom terms from admin</p>')
            ->assertJsonPath('data.sections', []);

        $this->withToken($token)
            ->put('/api/admin/client/terms-and-conditions', [
                'page_title' => 'Terms & Conditions',
                'content_body' => '<p>Updated terms only</p>',
            ])
            ->assertOk();

        $this->getJson('/api/client/terms-and-conditions?lang=en')
            ->assertOk()
            ->assertJsonPath('data.intro', '<p>Updated terms only</p>')
            ->assertJsonPath('data.sections', []);

        $this->getJson('/api/client/terms-and-conditions?lang=en')
            ->assertJsonPath('data.sections', []);
    }

    public function test_admin_vendor_privacy_update_via_put_multipart_form_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $contentBody = '<p>Lorem Ipsum vendor privacy from multipart PUT</p>';
        $boundary = '----LegalFormBoundary7';
        $body = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"page_title\"\r\n\r\n"
            ."Privacy Policy\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"content_body\"\r\n\r\n"
            .$contentBody."\r\n"
            ."--{$boundary}--\r\n";

        $this->call(
            'PUT',
            '/api/admin/vendor/privacy-policy',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            ],
            $body
        )
            ->assertOk()
            ->assertJsonPath('data.audience', 'vendor')
            ->assertJsonPath('data.content_body', $contentBody);

        $this->getJson('/api/vendor/privacy-policy?lang=en')
            ->assertOk()
            ->assertJsonPath('data.body', $contentBody);
    }
}
