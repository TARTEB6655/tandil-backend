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

    public function test_public_lists_all_managed_cms_pages_with_audiences(): void
    {
        $this->getJson('/api/public/cms/pages')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.suggested_audiences', ['client', 'vendor'])
            ->assertJsonFragment(['slug' => 'privacy-policy'])
            ->assertJsonFragment(['slug' => 'terms-and-conditions'])
            ->assertJsonFragment(['slug' => 'contact-us']);
    }

    public function test_public_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/public/cms/pages/unknown-page?audience=client')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_public_rejects_invalid_audience(): void
    {
        $this->getJson('/api/public/cms/pages/privacy-policy?audience=technician')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['audience']);
    }

    public function test_public_privacy_returns_app_shaped_payload_for_vendor(): void
    {
        $this->getJson('/api/public/cms/pages/privacy-policy?audience=vendor&lang=en')
            ->assertOk()
            ->assertJsonPath('data.slug', 'privacy-policy')
            ->assertJsonPath('data.audience', 'vendor')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.title', 'Privacy Policy')
            ->assertJsonStructure([
                'data' => ['slug', 'audience', 'locale', 'title', 'subtitle', 'body'],
            ]);
    }

    public function test_public_terms_returns_sections_for_client(): void
    {
        $this->getJson('/api/public/cms/pages/terms-and-conditions?audience=client&lang=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Terms & Conditions')
            ->assertJsonPath('data.effective_date', 'July 9, 2026')
            ->assertJsonStructure([
                'data' => ['intro', 'sections' => [['number', 'title', 'body']]],
            ])
            ->assertJsonPath('data.sections.0.number', 1)
            ->assertJsonPath('data.sections.0.title', 'About Tandil');
    }

    public function test_public_contact_returns_reach_us_for_vendor(): void
    {
        $this->getJson('/api/public/cms/pages/contact-us?audience=vendor&lang=en')
            ->assertOk()
            ->assertJsonPath('data.subtitle', 'We are here to help vendors')
            ->assertJsonPath('data.hero.title', 'Get in touch with Tandil')
            ->assertJsonPath('data.company.name', 'Tandil')
            ->assertJsonStructure([
                'data' => [
                    'hero' => ['title', 'description'],
                    'company' => ['name', 'location'],
                    'reach_us' => [['type', 'label', 'value', 'subtitle']],
                    'response_notice',
                ],
            ]);

        $reachUs = $this->getJson('/api/public/cms/pages/contact-us?audience=vendor&lang=en')
            ->json('data.reach_us');
        $types = array_column($reachUs, 'type');
        $this->assertContains('website', $types);
        $this->assertContains('email', $types);
        $this->assertContains('whatsapp', $types);
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
            ->assertJsonPath('data.suggested_audiences', ['client', 'vendor'])
            ->assertJsonPath('data.suggested_locales', ['en', 'ar', 'ur']);

        $this->withToken($token)
            ->getJson('/api/admin/cms/pages/terms-and-conditions')
            ->assertOk()
            ->assertJsonPath('data.slug', 'terms-and-conditions')
            ->assertJsonStructure([
                'data' => [
                    'slug',
                    'label',
                    'translations' => ['client', 'vendor'],
                    'is_active',
                    'suggested_audiences',
                ],
            ]);
    }

    public function test_admin_can_update_vendor_terms_separately_from_client(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/terms-and-conditions', [
                'translations' => [
                    'client' => [
                        'en' => [
                            'title' => 'Client Terms',
                            'effective_date' => 'Jan 1, 2026',
                            'intro' => '<p>Client intro</p>',
                            'sections' => [['title' => 'Client section', 'body' => 'Client body']],
                        ],
                    ],
                    'vendor' => [
                        'en' => [
                            'title' => 'Vendor Terms',
                            'effective_date' => 'Feb 1, 2026',
                            'intro' => '<p>Vendor intro</p>',
                            'sections' => [['title' => 'Vendor section', 'body' => 'Vendor body']],
                        ],
                    ],
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.translations.vendor.en.title', 'Vendor Terms');

        $this->getJson('/api/public/cms/pages/terms-and-conditions?audience=client&lang=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Client Terms')
            ->assertJsonPath('data.sections.0.title', 'Client section');

        $this->getJson('/api/public/cms/pages/terms-and-conditions?audience=vendor&lang=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Vendor Terms')
            ->assertJsonPath('data.sections.0.title', 'Vendor section');
    }

    public function test_legal_settings_alias_get_and_put_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/settings/legal?type=privacy&audience=vendor')
            ->assertOk()
            ->assertJsonPath('data.slug', 'privacy-policy')
            ->assertJsonPath('data.audience', 'vendor')
            ->assertJsonStructure(['data' => ['translations', 'type', 'url']]);

        $this->withToken($token)
            ->putJson('/api/admin/settings/legal?type=terms', [
                'translations' => [
                    'client' => [
                        'en' => ['title' => 'Terms via alias', 'intro' => '<p>Alias update</p>'],
                    ],
                    'vendor' => [
                        'en' => ['title' => 'Vendor alias terms', 'intro' => '<p>Vendor alias</p>'],
                    ],
                ],
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.translations.vendor.en.title', 'Vendor alias terms');
    }

    public function test_non_admin_cannot_update_cms_pages(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $token = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/privacy-policy', [
                'translations' => [
                    'client' => ['en' => ['title' => 'Hack', 'body' => 'x']],
                ],
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
                    'client' => ['en' => ['title' => 'Hidden', 'body' => '<p>Hidden</p>']],
                    'vendor' => ['en' => ['title' => 'Hidden', 'body' => '<p>Hidden</p>']],
                ],
                'is_active' => false,
            ])
            ->assertOk();

        $this->getJson('/api/public/cms/pages/privacy-policy?audience=client')
            ->assertNotFound();
    }

    public function test_help_center_uses_client_cms_contact_details(): void
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
                    'client' => [
                        'en' => [
                            'title' => 'Contact',
                            'subtitle' => 'We are here to help you',
                            'hero_title' => 'Get in touch',
                            'hero_description' => 'Client hero',
                            'response_notice' => '24h response',
                        ],
                    ],
                    'vendor' => [
                        'en' => [
                            'title' => 'Contact',
                            'subtitle' => 'We are here to help vendors',
                            'hero_title' => 'Vendor hero',
                            'hero_description' => 'Vendor hero body',
                            'response_notice' => '48h response',
                        ],
                    ],
                ],
                'contact_details' => [
                    'client' => [
                        'phone' => '+971599988877',
                        'whatsapp' => '+971599988877',
                        'email' => 'cms-contact@tandil.com',
                        'website' => 'tandil.ae',
                        'company_name' => 'Tandil',
                        'location' => ['en' => 'All UAE'],
                        'working_hours' => ['en' => 'Daily 8am-8pm'],
                        'service_areas' => ['en' => 'All UAE'],
                    ],
                    'vendor' => [
                        'phone' => '+971500000001',
                        'whatsapp' => '+971500000001',
                        'email' => 'vendor@tandil.com',
                        'website' => 'tandil.ae',
                        'company_name' => 'Tandil',
                        'location' => ['en' => 'UAE'],
                    ],
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
                    'client' => [
                        'en' => ['title' => 'Web Save', 'subtitle' => 'Saved subtitle', 'body' => '<p>Saved from admin form</p>'],
                    ],
                    'vendor' => [
                        'en' => ['title' => 'Vendor Web Save', 'subtitle' => 'Vendor subtitle', 'body' => '<p>Vendor saved</p>'],
                    ],
                ],
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.cms-pages.edit', 'privacy-policy'));

        $this->getJson('/api/public/cms/pages/privacy-policy?audience=vendor&lang=en')
            ->assertOk()
            ->assertJsonPath('data.title', 'Vendor Web Save');
    }

    public function test_admin_can_update_contact_us_details_per_audience(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/contact-us', [
                'translations' => [
                    'client' => [
                        'en' => [
                            'title' => 'Contact Us',
                            'subtitle' => 'Client subtitle',
                            'hero_title' => 'Client hero',
                            'hero_description' => 'Client hero body',
                            'response_notice' => 'Client notice',
                        ],
                    ],
                    'vendor' => [
                        'en' => [
                            'title' => 'Contact Us',
                            'subtitle' => 'Vendor subtitle',
                            'hero_title' => 'Vendor hero',
                            'hero_description' => 'Vendor hero body',
                            'response_notice' => 'Vendor notice',
                        ],
                    ],
                ],
                'contact_details' => [
                    'client' => [
                        'website' => 'client.tandil.ae',
                        'email' => 'hello@tandil.com',
                        'whatsapp' => '+971501112233',
                    ],
                    'vendor' => [
                        'website' => 'vendor.tandil.ae',
                        'email' => 'vendors@tandil.com',
                        'whatsapp' => '+971509998877',
                    ],
                ],
                'is_active' => true,
            ])
            ->assertOk();

        $this->getJson('/api/public/cms/pages/contact-us?audience=vendor&lang=en')
            ->assertOk()
            ->assertJsonPath('data.subtitle', 'Vendor subtitle')
            ->assertJsonFragment(['value' => 'vendors@tandil.com']);
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
            ->assertSee('Contact Us')
            ->assertSee('Client App')
            ->assertSee('Vendor App');
    }

    public function test_public_terms_and_contact_routes_render_saved_content(): void
    {
        CmsPage::query()->create([
            'slug' => CmsPage::SLUG_TERMS,
            'label' => 'Terms & Conditions',
            'translations' => [
                'client' => [
                    'en' => ['title' => 'Terms & Conditions', 'intro' => '<p>Custom terms body</p>'],
                ],
                'vendor' => [
                    'en' => ['title' => 'Terms & Conditions', 'intro' => '<p>Vendor terms body</p>'],
                ],
            ],
            'is_active' => true,
        ]);

        $this->get('/terms-and-conditions?audience=client')
            ->assertOk()
            ->assertSee('Custom terms body', false);
    }

    public function test_all_cms_api_endpoints_respond_successfully(): void
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->getJson('/api/public/cms/pages')->assertOk();
        $this->getJson('/api/public/cms/pages/unknown?audience=client')->assertNotFound();

        foreach (['client', 'vendor'] as $audience) {
            foreach (['privacy-policy', 'terms-and-conditions', 'contact-us'] as $slug) {
                $response = $this->getJson("/api/public/cms/pages/{$slug}?audience={$audience}&lang=en");
                $response->assertOk()->assertJsonPath('data.audience', $audience)->assertJsonPath('data.slug', $slug);
            }
        }

        $this->withToken($token)->getJson('/api/admin/cms/pages')->assertOk();
        foreach (['privacy-policy', 'terms-and-conditions', 'contact-us'] as $slug) {
            $this->withToken($token)
                ->getJson("/api/admin/cms/pages/{$slug}")
                ->assertOk()
                ->assertJsonPath('data.slug', $slug)
                ->assertJsonStructure(['data' => ['translations' => ['client', 'vendor']]]);
        }

        $this->withToken($token)
            ->putJson('/api/admin/cms/pages/privacy-policy', [
                'translations' => [
                    'client' => ['en' => ['title' => 'Client Privacy', 'subtitle' => 'Client sub', 'body' => '<p>Client</p>']],
                    'vendor' => ['en' => ['title' => 'Vendor Privacy', 'subtitle' => 'Vendor sub', 'body' => '<p>Vendor</p>']],
                ],
                'is_active' => true,
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/admin/settings/legal?type=privacy&audience=client')
            ->assertOk()
            ->assertJsonPath('data.slug', 'privacy-policy');

        $this->withToken($token)
            ->getJson('/api/admin/settings/legal?type=terms&audience=vendor')
            ->assertOk()
            ->assertJsonPath('data.slug', 'terms-and-conditions');

        $this->withToken($token)
            ->putJson('/api/admin/settings/legal?type=privacy', [
                'translations' => [
                    'client' => ['en' => ['title' => 'Legal Alias Privacy', 'subtitle' => 'Alias', 'body' => '<p>Alias</p>']],
                    'vendor' => ['en' => ['title' => 'Vendor Legal Alias', 'subtitle' => 'Alias', 'body' => '<p>Alias vendor</p>']],
                ],
                'is_active' => true,
            ])
            ->assertOk();

        $this->actingAs($admin)->get(route('admin.cms-pages.index'))->assertOk();
        foreach (['privacy-policy', 'terms-and-conditions', 'contact-us'] as $slug) {
            $this->actingAs($admin)->get(route('admin.cms-pages.edit', $slug))->assertOk();
        }

        $this->get('/privacy-policy?audience=client')->assertOk();
        $this->get('/contact-us?audience=vendor')->assertOk();
    }
}
