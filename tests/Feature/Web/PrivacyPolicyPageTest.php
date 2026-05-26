<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_page_is_public_and_returns_html(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertStatus(200);
        $response->assertSee('Privacy Policy', false);
        $response->assertSee('Effective Date', false);
        $response->assertSee('2026', false);
        $response->assertSee('info@tandil.ae', false);
    }

    public function test_privacy_alias_redirects_to_privacy_policy(): void
    {
        $this->get('/privacy')
            ->assertRedirect('/privacy-policy');
    }
}
