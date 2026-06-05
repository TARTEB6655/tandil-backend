<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorDashboardController extends Controller
{
    public function __construct(
        private readonly VendorDashboardService $dashboard
    ) {
        $this->middleware(['auth', 'role:vendor']);
        $this->middleware('vendor.approved')->only('index');
    }

    public function index(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');
        $overview = $this->dashboard->overview($vendor);
        $stats = $overview;
        $analytics = $overview['analytics'];

        return view('vendor.dashboard', [
            'vendor' => $vendor->load('profile'),
            'stats' => $stats,
            'analytics' => $analytics,
            'dashboardTitle' => 'Vendor Dashboard',
            'dashboardSubtitle' => 'Welcome back, '.($vendor->profile?->business_name ?? $request->user()->name).'. Here is your store performance overview.',
        ]);
    }

    public function pending(): View
    {
        return view('vendor.pending');
    }
}
