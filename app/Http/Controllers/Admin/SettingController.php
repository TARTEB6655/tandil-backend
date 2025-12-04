<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        return view('admin.settings.index');
    }

    public function updateAppSettings(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Update .env or config file
        // For now, just show success message
        // In production, you'd update config files or database
        
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('images', 'public');
            // Save logo path to config or database
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

        // Save payment settings
        return redirect()->back()->with('success', 'Payment settings updated successfully');
    }

    public function updateNotificationSettings(Request $request)
    {
        $request->validate([
            'firebase_server_key' => 'nullable|string',
            'firebase_sender_id' => 'nullable|string',
        ]);

        // Save notification settings
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
        ]);

        // Save email settings
        return redirect()->back()->with('success', 'Email settings updated successfully');
    }
}

