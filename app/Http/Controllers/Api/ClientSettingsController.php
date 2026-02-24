<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Client dashboard settings API.
 * Admin configures these in web panel; client app reads via GET.
 */
class ClientSettingsController extends Controller
{
    /**
     * GET /api/client/settings/sections
     * Returns all Profile settings sections in one place (for building Profile screen menu).
     * Each section has id, title, and path to call for data. Auth: client.
     */
    public function sections(): JsonResponse
    {
        $sections = [
            ['id' => 'memberships', 'title' => 'Memberships', 'path' => '/api/client/memberships', 'method' => 'GET'],
            ['id' => 'personal_information', 'title' => 'Personal Information', 'path' => '/api/user/profile', 'method' => 'GET'],
            ['id' => 'addresses', 'title' => 'Addresses', 'path' => '/api/user/addresses', 'method' => 'GET'],
            ['id' => 'payment_methods', 'title' => 'Payment Methods', 'path' => '/api/user/payment-methods', 'method' => 'GET'],
            ['id' => 'notifications', 'title' => 'Notifications', 'path' => '/api/user/notifications', 'method' => 'GET'],
            ['id' => 'help_support', 'title' => 'Help & Support', 'path' => '/api/support/help-center', 'method' => 'GET'],
        ];

        return ApiResponse::success('Profile settings sections retrieved.', $sections);
    }

    /**
     * GET /api/client/memberships
     * Returns the same subscription data as GET /api/subscriptions for the current client.
     * Client profile "Memberships" screen shows the user's subscriptions (plan, dates, amount, visits, etc.).
     * Auth: client.
     */
    public function memberships(Request $request): JsonResponse
    {
        $user = $request->user();
        $subs = Subscription::where('client_id', $user->id)
            ->with('visits')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Memberships retrieved successfully.',
            'data' => $subs->toArray(),
            'total' => $subs->count(),
        ]);
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
