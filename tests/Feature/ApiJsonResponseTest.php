<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ensure every API (all routes under /api/) returns JSON only, never HTML or raw error.
 * All routes in routes/api.php use the api middleware group and get ForceJsonResponse + EnsureApiJsonResponse.
 */
class ApiJsonResponseTest extends TestCase
{
    use RefreshDatabase;

    /** Assert response is JSON and not HTML. */
    private function assertApiResponseIsJsonNotHtml($response): void
    {
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $content);
        $this->assertStringNotContainsString('<html', $content);
    }

    /**
     * API 404 (route not found) must return JSON, never HTML.
     */
    public function test_api_404_returns_json_not_html(): void
    {
        $response = $this->getJson('/api/nonexistent-route-'.uniqid());
        $response->assertStatus(404)->assertJsonStructure(['success', 'message'])->assertJsonPath('success', false);
        $this->assertApiResponseIsJsonNotHtml($response);
    }

    /**
     * API 405 (method not allowed) must return JSON, never HTML.
     */
    public function test_api_405_returns_json_not_html(): void
    {
        $response = $this->getJson('/api/auth/login');
        $response->assertStatus(405)->assertJsonStructure(['success', 'message'])->assertJsonPath('success', false);
        $this->assertApiResponseIsJsonNotHtml($response);
    }

    /**
     * API 401 (unauthenticated) must return JSON, never HTML.
     */
    public function test_api_401_returns_json_not_html(): void
    {
        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(401)->assertJsonPath('success', false);
        $this->assertApiResponseIsJsonNotHtml($response);
    }

    /**
     * All API areas: protected routes return 401 JSON; public/success return JSON (never HTML).
     * Covers: health, auth, subscriptions, shop, visits, tech, supervisor, admin, reports, users, settings, tips, areas, services, orders, user, banners.
     */
    public function test_all_api_areas_return_json_never_html(): void
    {
        $protectedEndpoints = [
            'GET /api/auth/profile' => '/api/auth/profile',
            'GET /api/subscriptions' => '/api/subscriptions',
            'GET /api/visits' => '/api/visits',
            'GET /api/tech/visits' => '/api/tech/visits',
            'GET /api/supervisor/visits' => '/api/supervisor/visits',
            'GET /api/admin/products' => '/api/admin/products',
            'GET /api/admin/roles' => '/api/admin/roles',
            'GET /api/admin/hr/employees' => '/api/admin/hr/employees',
            'GET /api/reports' => '/api/reports',
            'GET /api/admin/dashboard/statistics' => '/api/admin/dashboard/statistics',
            'GET /api/admin/reports' => '/api/admin/reports',
            'GET /api/admin/settings' => '/api/admin/settings',
            'GET /api/tips' => '/api/tips',
            'GET /api/areas' => '/api/areas',
            'GET /api/orders' => '/api/orders',
            'GET /api/shop/cart' => '/api/shop/cart',
            'GET /api/user/profile' => '/api/user/profile',
        ];

        foreach ($protectedEndpoints as $label => $url) {
            $response = $this->getJson($url);
            $response->assertStatus(401);
            $response->assertJsonPath('success', false);
            $this->assertApiResponseIsJsonNotHtml($response);
        }
    }

    /**
     * Public API endpoints return JSON (success or error), never HTML.
     */
    public function test_public_api_endpoints_return_json_never_html(): void
    {
        $publicEndpoints = [
            '/api/health',
            '/api/subscriptions/plans',
            '/api/shop/products',
            '/api/shop/products/categories',
            '/api/shop/categories',
            '/api/services',
            '/api/services/categories',
            '/api/banners',
        ];

        foreach ($publicEndpoints as $url) {
            $response = $this->getJson($url);
            $this->assertApiResponseIsJsonNotHtml($response);
            $this->assertTrue($response->status() >= 200 && $response->status() < 600);
            // Body must be valid JSON (getJson already decodes)
            $data = $response->json();
            $this->assertTrue(is_array($data) || is_object($data) || $data === null);
        }
    }
}
