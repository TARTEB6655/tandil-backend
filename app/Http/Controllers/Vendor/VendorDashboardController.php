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
        $summary = $this->dashboard->dashboardSummaryMetrics($vendor);
        $analytics = $this->dashboard->analytics($vendor);
        $stats = $this->dashboard->stats($vendor);

        return view('vendor.dashboard', [
            'vendor' => $vendor->load('profile'),
            'summary' => $summary,
            'stats' => $stats,
            'analytics' => $analytics,
            'dashboardTitle' => 'Dashboard',
            'dashboardSubtitle' => 'Welcome back. Overview of your '.($vendor->profile?->business_name ?? 'store').'.',
        ]);
    }

    public function pending()
    {
        return redirect()->route('vendor.application.status');
    }
}
