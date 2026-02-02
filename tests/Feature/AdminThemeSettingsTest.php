<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_user_cannot_access_theme_settings_page(): void
    {
        $this->get(route('admin.settings.theme'))->assertRedirect(route('login'));
    }

    /** @test */
    public function admin_can_see_theme_settings_page(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $response = $this->get(route('admin.settings.theme'));

        $response->assertStatus(200);
        $response->assertSee('Theme Settings');
        $response->assertSee('App theme');
        $response->assertSee('System default');
        $response->assertSee('Light');
        $response->assertSee('Dark');
    }

    /** @test */
    public function saving_dark_theme_persists_and_next_page_load_shows_dark_class(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        // Get theme page and extract CSRF token
        $getResponse = $this->get(route('admin.settings.theme'));
        $getResponse->assertStatus(200);

        $content = $getResponse->getContent();
        $this->assertNotEmpty($content);

        // Extract _token from the form (Blade @csrf)
        preg_match('/name="_token"\s+value="([^"]+)"/', $content, $tokenMatch);
        $this->assertNotEmpty($tokenMatch[1] ?? null, 'CSRF token should be present in theme settings form');
        $token = $tokenMatch[1];

        // Post theme=dark
        $postResponse = $this->post(route('admin.settings.theme.store'), [
            '_token' => $token,
            'theme' => 'dark',
        ]);

        $postResponse->assertRedirect();
        $postResponse->assertSessionHas('success', 'Theme updated.');

        // Verify setting was persisted
        $this->assertSame('dark', Setting::get('app_theme', 'system'));

        // Get theme page again (simulate user landing after redirect) – must see dark class on html
        $getAfter = $this->get(route('admin.settings.theme'));
        $getAfter->assertStatus(200);

        $html = $getAfter->getContent();
        $this->assertStringContainsString('data-theme="dark"', $html, 'HTML should have data-theme="dark" after saving dark theme');
        $this->assertStringContainsString('class="dark"', $html, 'HTML should have class="dark" on html element after saving dark theme');
    }

    /** @test */
    public function saving_light_theme_removes_dark_class_on_next_load(): void
    {
        Setting::set('app_theme', 'dark', 'text', 'app_config');
        $this->assertSame('dark', Setting::get('app_theme', 'system'));

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $getResponse = $this->get(route('admin.settings.theme'));
        $content = $getResponse->getContent();
        preg_match('/name="_token"\s+value="([^"]+)"/', $content, $tokenMatch);
        $token = $tokenMatch[1] ?? '';

        $this->post(route('admin.settings.theme.store'), [
            '_token' => $token,
            'theme' => 'light',
        ]);

        $this->assertSame('light', Setting::get('app_theme', 'system'));

        $getAfter = $this->get(route('admin.settings.theme'));
        $html = $getAfter->getContent();
        $this->assertStringContainsString('data-theme="light"', $html);
        // When theme is light, html tag should not have class="dark" (it has class="")
        $this->assertTrue(
            !preg_match('/<html[^>]*\bclass="[^"]*dark[^"]*"/', $html),
            'HTML element should not have class containing "dark" when theme is light'
        );
    }
}
