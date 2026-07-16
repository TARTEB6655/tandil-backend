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
