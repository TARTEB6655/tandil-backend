<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorRegistrationRequest;
use App\Models\Category;
use App\Models\VendorProfile;
use App\Services\Vendor\VendorRegistrationService;

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
        $this->registration->register(
            $request->validated(),
            $request->file('logo'),
            VendorRegistrationService::documentFilesFromRequest($request)
        );

        return redirect()->route('app-portal.login', ['portal' => 'vendor'])->with(
            'success',
            'Registration submitted successfully. You can sign in after admin approves your vendor account.'
        );
    }
}
