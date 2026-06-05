<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly VendorApplicationService $application
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.account']);
    }

    public function status(Request $request): RedirectResponse
    {
        // Application & onboarding are now a single unified page.
        if ($request->attributes->get('vendor')->isApproved()) {
            return redirect()->route('vendor.dashboard');
        }

        return redirect()->route('vendor.onboarding.index');
    }

    public function resubmit(Request $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            $this->application->resubmit($vendor);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vendor.onboarding.index')->with('success', 'Application resubmitted for admin review.');
    }

    public function submit(Request $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');

        try {
            $this->application->markOnboardingSubmitted($vendor);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vendor.onboarding.index')->with('success', 'Application submitted for review.');
    }
}
