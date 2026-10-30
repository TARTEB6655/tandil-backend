<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminVendorTreePalmRouteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_palm_pricing_routes_are_registered(): void
    {
        $matches = collect(Route::getRoutes())->filter(function ($route) {
            $uri = (string) $route->uri();

            return str_contains($uri, 'admin/vendors/{id}/pricing/tree-palm')
                && in_array('POST', $route->methods(), true);
        });

        $this->assertGreaterThan(0, $matches->count(), 'POST admin/vendors/{id}/pricing/tree-palm must be registered.');
    }

    public function test_health_lists_vendor_tree_palm_pricing_routes(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $routes = $response->json('vendor_tree_palm_pricing_routes');
        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
        $this->assertTrue(
            collect($routes)->contains(fn (string $line) => str_contains($line, 'pricing/tree-palm')),
            'Health should expose vendor tree-palm pricing routes for deploy verification.'
        );
    }
}
