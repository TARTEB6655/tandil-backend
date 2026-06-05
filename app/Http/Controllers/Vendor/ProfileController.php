<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateVendorProfileRequest;
use App\Models\VendorProfile;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.account']);
    }

    public function show(Request $request): View
    {
        $vendor = $request->attributes->get('vendor')->load('profile', 'user', 'categories', 'documents');

        return view('vendor.profile.show', [
            'vendor' => $vendor,
            'vendorTypes' => VendorType::options(),
            'emirates' => VendorProfile::emirates(),
        ]);
    }

    public function update(UpdateVendorProfileRequest $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        $this->registration->updateProfile(
            $vendor,
            $request->validated(),
            $request->file('logo'),
            $request->boolean('logo_remove'),
            VendorRegistrationService::documentFilesFromRequest($request)
        );

        return redirect()->route('vendor.profile.show')->with('success', 'Business profile updated.');
    }
}
