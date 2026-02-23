<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Main settings dashboard (mobile-style: System, App Config, Data & Privacy, Advanced).
     */
    public function index()
    {
        $system = [
            'push_notifications_enabled' => (bool) (Setting::get('push_notifications_enabled', '1') === '1'),
            'auto_assign_tasks' => (bool) (Setting::get('auto_assign_tasks', '0') === '1'),
            'maintenance_mode' => (bool) (Setting::get('maintenance_mode', '0') === '1'),
        ];
        return view('admin.settings.dashboard', compact('system'));
    }

    /**
     * Full settings page (all sections: General, Contact, Payment, etc.).
     */
    public function all()
    {
        $settings = Setting::all()->groupBy('group');
        return view('admin.settings.all', compact('settings'));
    }

    public function updateSystem(Request $request)
    {
        $request->validate([
            'push_notifications_enabled' => 'nullable|boolean',
            'auto_assign_tasks' => 'nullable|boolean',
            'maintenance_mode' => 'nullable|boolean',
        ]);
        if ($request->has('push_notifications_enabled')) {
            Setting::set('push_notifications_enabled', $request->boolean('push_notifications_enabled') ? '1' : '0', 'text', 'system');
        }
        if ($request->has('auto_assign_tasks')) {
            Setting::set('auto_assign_tasks', $request->boolean('auto_assign_tasks') ? '1' : '0', 'text', 'system');
        }
        if ($request->has('maintenance_mode')) {
            Setting::set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0', 'text', 'system');
        }
        return redirect()->back()->with('success', 'System settings updated.');
    }

    public function theme()
    {
        $current = Setting::get('app_theme', 'system');
        $available = ['system' => 'System default', 'light' => 'Light', 'dark' => 'Dark'];
        return view('admin.settings.theme', compact('current', 'available'));
    }

    public function updateTheme(Request $request)
    {
        $request->validate(['theme' => 'required|in:system,light,dark']);
        Setting::set('app_theme', trim($request->theme), 'text', 'app_config');
        return redirect()
            ->back()
            ->with('success', 'Theme updated.')
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }

    public function language()
    {
        $language = Setting::get('app_language', 'en');
        $region = Setting::get('app_region', '');
        $available = [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'العربية']];
        return view('admin.settings.language', compact('language', 'region', 'available'));
    }

    public function updateLanguage(Request $request)
    {
        $request->validate(['language' => 'required|string|max:10', 'region' => 'nullable|string|max:10']);
        Setting::set('app_language', $request->language, 'text', 'app_config');
        Setting::set('app_region', $request->region ?? '', 'text', 'app_config');
        return redirect()->back()->with('success', 'Language & region updated.');
    }

    public function privacyPolicy()
    {
        $url = Setting::get('privacy_policy_url', '');
        $content = Setting::get('privacy_policy_content', '');
        return view('admin.settings.privacy-policy', compact('url', 'content'));
    }

    public function updatePrivacyPolicy(Request $request)
    {
        $request->validate(['privacy_policy_url' => 'nullable|url', 'privacy_policy_content' => 'nullable|string']);
        Setting::set('privacy_policy_url', $request->privacy_policy_url ?? '', 'text', 'legal');
        Setting::set('privacy_policy_content', $request->privacy_policy_content ?? '', 'text', 'legal');
        return redirect()->back()->with('success', 'Privacy policy updated.');
    }

    public function termsOfService()
    {
        $url = Setting::get('terms_of_service_url', '');
        $content = Setting::get('terms_of_service_content', '');
        return view('admin.settings.terms', compact('url', 'content'));
    }

    public function updateTermsOfService(Request $request)
    {
        $request->validate(['terms_of_service_url' => 'nullable|url', 'terms_of_service_content' => 'nullable|string']);
        Setting::set('terms_of_service_url', $request->terms_of_service_url ?? '', 'text', 'legal');
        Setting::set('terms_of_service_content', $request->terms_of_service_content ?? '', 'text', 'legal');
        return redirect()->back()->with('success', 'Terms of service updated.');
    }

    public function clearCache(Request $request)
    {
        Cache::flush();
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        return redirect()->back()->with('success', 'Cache cleared successfully.');
    }

    public function developerOptions()
    {
        $debug = config('app.debug');
        $env = app()->environment();
        return view('admin.settings.developer-options', compact('debug', 'env'));
    }

    public function debugLogs(Request $request)
    {
        $lines = min(max((int) $request->input('lines', 100), 10), 500);
        $path = storage_path('logs/laravel.log');
        $log = '';
        if (file_exists($path)) {
            $log = implode('', array_slice(file($path), -$lines));
        }
        return view('admin.settings.debug-logs', compact('lines', 'log'));
    }

    public function exportData(Request $request)
    {
        $format = $request->input('format', 'json');
        if (! in_array($format, ['json', 'csv'], true)) {
            return redirect()->back()->with('error', 'Invalid format.');
        }
        $exportId = 'export-' . uniqid();
        Setting::set('last_export_id', $exportId, 'text', 'system');
        Setting::set('last_export_at', now()->toIso8601String(), 'text', 'system');
        return redirect()->back()->with('success', 'Export requested. ID: ' . $exportId);
    }

    public function general()
    {
        $appName = Setting::get('app_name', config('app.name'));
        $logo = Setting::get('logo');
        $primaryColor = Setting::get('primary_color', '#6366f1');
        $secondaryColor = Setting::get('secondary_color', '#8b5cf6');
        return view('admin.settings.general', compact('appName', 'logo', 'primaryColor', 'secondaryColor'));
    }

    public function contact()
    {
        return view('admin.settings.contact');
    }

    public function social()
    {
        return view('admin.settings.social');
    }

    public function payment()
    {
        return view('admin.settings.payment');
    }

    public function email()
    {
        $appName = Setting::get('app_name', config('app.name'));
        return view('admin.settings.email', compact('appName'));
    }

    public function notifications()
    {
        return view('admin.settings.notifications');
    }

    public function security()
    {
        return view('admin.settings.security');
    }

    public function integrations()
    {
        return view('admin.settings.integrations');
    }

    public function updateAppSettings(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
        ]);

        // Update app name
        Setting::set('app_name', $request->app_name, 'text', 'branding');

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('images', 'public');
            \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($logoPath);
            Setting::set('logo', $logoPath, 'image', 'branding');
        }

        // Update colors
        if ($request->primary_color) {
            Setting::set('primary_color', $request->primary_color, 'text', 'branding');
        }
        if ($request->secondary_color) {
            Setting::set('secondary_color', $request->secondary_color, 'text', 'branding');
        }

        return redirect()->back()->with('success', 'App settings updated successfully');
    }

    public function updatePaymentSettings(Request $request)
    {
        $request->validate([
            'payment_gateway' => 'required|in:stripe,paymob,ccavenue,tap',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
        ]);

        Setting::set('payment_gateway', $request->payment_gateway, 'text', 'payment');
        Setting::set('payment_api_key', $request->api_key, 'text', 'payment');
        Setting::set('payment_api_secret', $request->api_secret, 'text', 'payment');

        return redirect()->back()->with('success', 'Payment settings updated successfully');
    }

    public function updateNotificationSettings(Request $request)
    {
        $request->validate([
            'firebase_server_key' => 'nullable|string',
            'firebase_sender_id' => 'nullable|string',
        ]);

        Setting::set('firebase_server_key', $request->firebase_server_key, 'text', 'notification');
        Setting::set('firebase_sender_id', $request->firebase_sender_id, 'text', 'notification');

        return redirect()->back()->with('success', 'Notification settings updated successfully');
    }

    public function updateEmailSettings(Request $request)
    {
        $request->validate([
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'smtp_username' => 'required|string',
            'smtp_password' => 'required|string',
            'smtp_encryption' => 'required|in:tls,ssl',
            'smtp_from_email' => 'required|email',
            'smtp_from_name' => 'required|string',
        ]);

        Setting::set('smtp_host', $request->smtp_host, 'text', 'email');
        Setting::set('smtp_port', $request->smtp_port, 'text', 'email');
        Setting::set('smtp_username', $request->smtp_username, 'text', 'email');
        Setting::set('smtp_password', $request->smtp_password, 'text', 'email');
        Setting::set('smtp_encryption', $request->smtp_encryption, 'text', 'email');
        Setting::set('smtp_from_email', $request->smtp_from_email, 'text', 'email');
        Setting::set('smtp_from_name', $request->smtp_from_name, 'text', 'email');

        return redirect()->back()->with('success', 'Email settings updated successfully');
    }

    public function updateSocialSettings(Request $request)
    {
        $request->validate([
            'facebook_url' => 'nullable|url',
            'twitter_url' => 'nullable|url',
            'instagram_url' => 'nullable|url',
            'linkedin_url' => 'nullable|url',
            'youtube_url' => 'nullable|url',
        ]);

        Setting::set('facebook_url', $request->facebook_url, 'text', 'social');
        Setting::set('twitter_url', $request->twitter_url, 'text', 'social');
        Setting::set('instagram_url', $request->instagram_url, 'text', 'social');
        Setting::set('linkedin_url', $request->linkedin_url, 'text', 'social');
        Setting::set('youtube_url', $request->youtube_url, 'text', 'social');

        return redirect()->back()->with('success', 'Social links updated successfully');
    }

    public function updateContactSettings(Request $request)
    {
        $request->validate([
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'contact_address' => 'nullable|string',
            'support_hours' => 'nullable|string|max:255',
        ]);

        Setting::set('contact_email', $request->contact_email ?? '', 'text', 'general');
        Setting::set('contact_phone', $request->contact_phone ?? '', 'text', 'general');
        Setting::set('contact_address', $request->contact_address ?? '', 'text', 'general');
        Setting::set('support_hours', $request->support_hours ?? '24/7 Customer Support', 'text', 'general');

        return redirect()->back()->with('success', 'Contact information updated successfully');
    }

    /**
     * Email Templates Management
     */
    public function emailTemplates()
    {
        $templates = EmailTemplate::orderBy('name')->get();
        return view('admin.settings.email-templates', compact('templates'));
    }

    public function updateEmailTemplate(Request $request, $id)
    {
        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $template->update($validated);

        return redirect()->back()->with('success', 'Email template updated successfully.');
    }

    /**
     * Security Settings
     */
    public function updateSecuritySettings(Request $request)
    {
        $validated = $request->validate([
            'password_min_length' => 'nullable|integer|min:6|max:32',
            'password_require_uppercase' => 'boolean',
            'password_require_lowercase' => 'boolean',
            'password_require_numbers' => 'boolean',
            'password_require_symbols' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'login_attempts_limit' => 'nullable|integer|min:3|max:10',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value, is_bool($value) ? 'boolean' : 'text', 'security');
        }

        return redirect()->back()->with('success', 'Security settings updated successfully.');
    }

    /**
     * Integrations Settings
     */
    public function updateIntegrationsSettings(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_api_key' => 'nullable|string',
            'whatsapp_api_secret' => 'nullable|string',
            'whatsapp_phone_number' => 'nullable|string',
            'firebase_server_key' => 'nullable|string',
            'firebase_sender_id' => 'nullable|string',
            'google_maps_api_key' => 'nullable|string',
            'webhook_url' => 'nullable|url',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value ?? '', 'text', 'integrations');
        }

        return redirect()->back()->with('success', 'Integrations settings updated successfully.');
    }

    /**
     * Client (Customer) Dashboard Design – control what customers see on their dashboard.
     * Admin can set title, subtitle, and toggle each section visibility.
     */
    public function clientDashboardDesign()
    {
        $title = Setting::get('client_dashboard_title', 'My Dashboard');
        $subtitle = Setting::get('client_dashboard_subtitle', "Welcome back! Here's an overview of your subscriptions, visits, and orders.");
        $showBanners = (Setting::get('client_dashboard_show_banners', '1') === '1');
        $showMetrics = (Setting::get('client_dashboard_show_metrics', '1') === '1');
        $showSecondaryMetrics = (Setting::get('client_dashboard_show_secondary_metrics', '1') === '1');
        $showCharts = (Setting::get('client_dashboard_show_charts', '1') === '1');
        $showRecentSubscriptions = (Setting::get('client_dashboard_show_recent_subscriptions', '1') === '1');
        $showRecentVisits = (Setting::get('client_dashboard_show_recent_visits', '1') === '1');
        $showRecentReports = (Setting::get('client_dashboard_show_recent_reports', '1') === '1');
        $showRecentOrders = (Setting::get('client_dashboard_show_recent_orders', '1') === '1');
        $showRecentComplaints = (Setting::get('client_dashboard_show_recent_complaints', '1') === '1');

        return view('admin.settings.client-dashboard', compact(
            'title', 'subtitle',
            'showBanners', 'showMetrics', 'showSecondaryMetrics', 'showCharts',
            'showRecentSubscriptions', 'showRecentVisits', 'showRecentReports',
            'showRecentOrders', 'showRecentComplaints'
        ));
    }

    public function updateClientDashboardDesign(Request $request)
    {
        $request->validate([
            'client_dashboard_title' => 'nullable|string|max:255',
            'client_dashboard_subtitle' => 'nullable|string|max:500',
            'client_dashboard_show_banners' => 'nullable|boolean',
            'client_dashboard_show_metrics' => 'nullable|boolean',
            'client_dashboard_show_secondary_metrics' => 'nullable|boolean',
            'client_dashboard_show_charts' => 'nullable|boolean',
            'client_dashboard_show_recent_subscriptions' => 'nullable|boolean',
            'client_dashboard_show_recent_visits' => 'nullable|boolean',
            'client_dashboard_show_recent_reports' => 'nullable|boolean',
            'client_dashboard_show_recent_orders' => 'nullable|boolean',
            'client_dashboard_show_recent_complaints' => 'nullable|boolean',
        ]);

        $keys = [
            'client_dashboard_title' => $request->input('client_dashboard_title', 'My Dashboard'),
            'client_dashboard_subtitle' => $request->input('client_dashboard_subtitle', "Welcome back! Here's an overview of your subscriptions, visits, and orders."),
            'client_dashboard_show_banners' => $request->boolean('client_dashboard_show_banners') ? '1' : '0',
            'client_dashboard_show_metrics' => $request->boolean('client_dashboard_show_metrics') ? '1' : '0',
            'client_dashboard_show_secondary_metrics' => $request->boolean('client_dashboard_show_secondary_metrics') ? '1' : '0',
            'client_dashboard_show_charts' => $request->boolean('client_dashboard_show_charts') ? '1' : '0',
            'client_dashboard_show_recent_subscriptions' => $request->boolean('client_dashboard_show_recent_subscriptions') ? '1' : '0',
            'client_dashboard_show_recent_visits' => $request->boolean('client_dashboard_show_recent_visits') ? '1' : '0',
            'client_dashboard_show_recent_reports' => $request->boolean('client_dashboard_show_recent_reports') ? '1' : '0',
            'client_dashboard_show_recent_orders' => $request->boolean('client_dashboard_show_recent_orders') ? '1' : '0',
            'client_dashboard_show_recent_complaints' => $request->boolean('client_dashboard_show_recent_complaints') ? '1' : '0',
        ];

        foreach ($keys as $key => $value) {
            Setting::set($key, $value, 'text', 'client_dashboard');
        }

        return redirect()->route('admin.settings.client-dashboard')->with('success', 'Customer dashboard design updated. Customers will see the new layout on their next visit.');
    }
}
