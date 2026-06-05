<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorRegistrationRequest;
use App\Models\Category;
use App\Models\VendorProfile;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Support\Facades\Auth;

class VendorRegistrationController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration
    ) {}

    public function create()
    {
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('vendor.register', [
            'categories' => $categories,
            'vendorTypes' => VendorType::options(),
            'emirates' => VendorProfile::emirates(),
        ]);
    }

    public function store(VendorRegistrationRequest $request)
    {
        $vendor = $this->registration->register(
            $request->validated(),
            $request->file('logo'),
            VendorRegistrationService::documentFilesFromRequest($request)
        );

        Auth::login($vendor->user);

        return redirect()->route('vendor.onboarding.index')->with('success', 'Account created. Complete onboarding to submit your application.');
    }
}
