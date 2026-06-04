<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorRegistrationService;
use App\Support\VendorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function show(Request $request): View
    {
        $vendor = VendorContext::vendorForUser($request->user())?->load('profile', 'user');

        return view('vendor.profile.show', compact('vendor'));
    }

    public function update(Request $request): RedirectResponse
    {
        $vendor = VendorContext::vendorForUser($request->user());
        if ($vendor === null) {
            return redirect()->route('vendor.pending');
        }

        $data = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'owner_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:2000',
            'tax_vat_number' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:5000',
            'logo' => 'nullable|image|max:5120',
            'logo_remove' => 'nullable|boolean',
        ]);

        $this->registration->updateProfile(
            $vendor,
            $data,
            $request->file('logo'),
            $request->boolean('logo_remove')
        );

        return redirect()->route('vendor.profile.show')->with('success', 'Business profile updated.');
    }
}
