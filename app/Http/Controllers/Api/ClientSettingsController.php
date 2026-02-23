<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Client dashboard settings API.
 * Admin configures these in web panel; client app reads via GET.
 */
class ClientSettingsController extends Controller
{
    /**
     * GET /api/client/memberships
     * List memberships (packages) created by admin. For client profile "Memberships" screen.
     * Auth: client.
     */
    public function memberships(): JsonResponse
    {
        $packages = Package::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'type' => $p->type,
                'price' => (float) $p->price,
                'image' => $p->image,
                'image_url' => $p->image_url,
                'description' => $p->description,
            ]);

        return ApiResponse::success('Memberships retrieved successfully.', $packages->values()->all());
    }
    /**
     * GET /api/client/settings/dashboard
     * Returns client dashboard settings (title, subtitle, section toggles) for the app.
     * Auth: client role.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->getDashboardSettings(),
        ]);
    }

    protected function getDashboardSettings(): array
    {
        return [
            'title' => Setting::get('client_dashboard_title', 'My Dashboard'),
            'subtitle' => Setting::get('client_dashboard_subtitle', "Welcome back! Here's an overview of your subscriptions, visits, and orders."),
            'show_banners' => (Setting::get('client_dashboard_show_banners', '1') === '1'),
            'show_metrics' => (Setting::get('client_dashboard_show_metrics', '1') === '1'),
            'show_secondary_metrics' => (Setting::get('client_dashboard_show_secondary_metrics', '1') === '1'),
            'show_charts' => (Setting::get('client_dashboard_show_charts', '1') === '1'),
            'show_recent_subscriptions' => (Setting::get('client_dashboard_show_recent_subscriptions', '1') === '1'),
            'show_recent_visits' => (Setting::get('client_dashboard_show_recent_visits', '1') === '1'),
            'show_recent_reports' => (Setting::get('client_dashboard_show_recent_reports', '1') === '1'),
            'show_recent_orders' => (Setting::get('client_dashboard_show_recent_orders', '1') === '1'),
            'show_recent_complaints' => (Setting::get('client_dashboard_show_recent_complaints', '1') === '1'),
        ];
    }
}
