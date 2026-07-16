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
            ->assertJsonPath('data.sections.0.title', 'About Tandil');
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
}
