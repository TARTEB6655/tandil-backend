<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        // Get all settings grouped
        $settings = Setting::all()->groupBy('group');
        
        return view('admin.settings.index', compact('settings'));
    }

    public function updateAppSettings(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
        ]);

        // Update app name
        Setting::set('app_name', $request->app_name, 'text', 'branding');

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('images', 'public');
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
        ]);

        Setting::set('contact_email', $request->contact_email, 'text', 'general');
        Setting::set('contact_phone', $request->contact_phone, 'text', 'general');
        Setting::set('contact_address', $request->contact_address, 'text', 'general');

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
}
