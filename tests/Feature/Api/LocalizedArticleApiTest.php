<?php

namespace Tests\Feature\Api;

use App\Models\LocalizedArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizedArticleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\LocalizedArticleSeeder::class);
    }

    public function test_index_returns_english_by_default(): void
    {
        $response = $this->getJson('/api/localized-articles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.items.0.slug', 'welcome-guide')
            ->assertJsonPath('data.items.0.title', 'Welcome to Tandil');
    }

    public function test_index_respects_accept_language_arabic(): void
    {
        $response = $this->getJson('/api/localized-articles', [
            'Accept' => 'application/json',
            'Accept-Language' => 'ar',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.items.0.title', 'مرحبًا بك في تنديل');
    }

    public function test_show_respects_lang_query_urdu(): void
    {
        $response = $this->getJson('/api/localized-articles/welcome-guide?lang=ur');

        $response->assertStatus(200)
            ->assertJsonPath('data.locale', 'ur')
            ->assertJsonPath('data.title', 'تندیل میں خوش آمدید');
    }

    public function test_include_translations_returns_all_locales(): void
    {
        $response = $this->getJson('/api/localized-articles/welcome-guide?lang=en&include_translations=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.translations.title.en', 'Welcome to Tandil')
            ->assertJsonPath('data.translations.title.ar', 'مرحبًا بك في تنديل')
            ->assertJsonPath('data.translations.title.ur', 'تندیل میں خوش آمدید');
    }

    public function test_lang_query_overrides_accept_language(): void
    {
        $response = $this->getJson('/api/localized-articles/welcome-guide?lang=ar', [
            'Accept-Language' => 'ur',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.title', 'مرحبًا بك في تنديل');
    }

    public function test_fallback_when_translation_missing(): void
    {
        LocalizedArticle::query()->create([
            'slug' => 'en-only',
            'title' => ['en' => 'English only title'],
            'description' => ['en' => 'English only body'],
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $response = $this->getJson('/api/localized-articles/en-only?lang=ar');

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'English only title')
            ->assertJsonPath('data.used_fallback_for', ['title', 'description']);
    }
}
