<?php

namespace Tests\Unit;

use App\Models\Banner;
use App\Support\BannerLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerLinkResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_internal_route_name_stored_as_link_type(): void
    {
        $banner = Banner::create([
            'action_type' => 'link',
            'action_value' => 'client.shop.index',
            'link' => 'client.shop.index',
        ]);

        $href = BannerLinkResolver::resolve($banner);

        $this->assertNotNull($href);
        $this->assertStringContainsString('/client/shop', $href);
        $this->assertFalse(BannerLinkResolver::isExternalUrl($href));
    }

    public function test_resolves_route_action_type(): void
    {
        $banner = Banner::create([
            'action_type' => 'route',
            'action_value' => 'client.shop.index',
            'link' => null,
        ]);

        $this->assertSame(route('client.shop.index'), BannerLinkResolver::resolve($banner));
    }

    public function test_parse_admin_button_link_stores_route_type(): void
    {
        $parsed = BannerLinkResolver::parseAdminButtonLink('client.shop.index');

        $this->assertSame('route', $parsed['action_type']);
        $this->assertSame('client.shop.index', $parsed['action_value']);
        $this->assertNull($parsed['link']);
    }

    public function test_parse_admin_button_link_stores_https_url(): void
    {
        $parsed = BannerLinkResolver::parseAdminButtonLink('https://example.com/promo');

        $this->assertSame('link', $parsed['action_type']);
        $this->assertSame('https://example.com/promo', $parsed['action_value']);
    }

    public function test_invalid_route_name_returns_null_href(): void
    {
        $banner = Banner::create([
            'action_type' => 'link',
            'action_value' => 'not.a.real.route.name',
            'link' => null,
        ]);

        $this->assertNull(BannerLinkResolver::resolve($banner));
    }

    public function test_shop_shortcut_resolves(): void
    {
        $banner = Banner::create([
            'action_type' => 'link',
            'action_value' => 'shop',
            'link' => null,
        ]);

        $this->assertSame(route('client.shop.index'), BannerLinkResolver::resolve($banner));
    }
}
