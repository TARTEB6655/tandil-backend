<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateVendorProfileRequest;
use App\Models\Category;
use App\Models\VendorProfile;
use App\Services\Vendor\VendorApplicationService;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly VendorRegistrationService $registration,
        private readonly VendorApplicationService $application
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.account']);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        if ($vendor->isApproved()) {
            return redirect()->route('vendor.dashboard');
        }

        return view('vendor.onboarding.index', [
            'vendor' => $vendor,
            'application' => $this->application->applicationPayload($vendor),
        ]);
    }

    public function profile(Request $request): View|RedirectResponse
    {
        $vendor = $request->attributes->get('vendor')->load('profile', 'documents');
        if ($vendor->isApproved()) {
            return redirect()->route('vendor.dashboard');
        }

        return view('vendor.onboarding.profile', [
            'vendor' => $vendor,
            'vendorTypes' => VendorType::options(),
            'emirates' => VendorProfile::emirates(),
        ]);
    }

    public function updateProfile(UpdateVendorProfileRequest $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        if (! $vendor->statusEnum()->canCompleteOnboarding()) {
            return redirect()->route('vendor.application.status')->with('error', 'Profile cannot be edited in current status.');
        }

        $this->registration->updateProfile(
            $vendor,
            $request->validated(),
            $request->file('logo'),
            $request->boolean('logo_remove'),
            VendorRegistrationService::documentFilesFromRequest($request)
        );

        return redirect()->route('vendor.onboarding.categories')->with('success', 'Business profile saved.');
    }

    public function categories(Request $request): View|RedirectResponse
    {
        $vendor = $request->attributes->get('vendor')->load('categories');
        if ($vendor->isApproved()) {
            return redirect()->route('vendor.dashboard');
        }

        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('vendor.onboarding.categories', compact('vendor', 'categories'));
    }

    public function updateCategories(Request $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        $data = $request->validate([
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
        ]);

        $this->application->syncCategories($vendor, $data['category_ids']);

        return redirect()->route('vendor.documents.index')->with('success', 'Business categories saved.');
    }
}
