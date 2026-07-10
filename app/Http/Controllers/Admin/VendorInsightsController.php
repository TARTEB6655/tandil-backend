<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Vendor\AdminVendorInsightsService;
use App\Services\Vendor\AdminVendorOverviewService;
use App\Services\Vendor\AdminVendorRevenueService;
use Illuminate\View\View;

class VendorInsightsController extends Controller
{
    public function __construct(
        private readonly AdminVendorInsightsService $insights,
        private readonly AdminVendorOverviewService $overview,
        private readonly AdminVendorRevenueService $revenue
    ) {
        $this->middleware('role:admin');
    }

    public function overview(): View
    {
        return view('admin.vendors.overview', [
            'data' => $this->overview->dashboard(),
        ]);
    }

    public function reports(): View
    {
        return view('admin.vendors.reports', [
            'data' => $this->overview->dashboard(),
        ]);
    }

    public function index(): View
    {
        return view('admin.vendors.insights', [
            'data' => $this->insights->dashboard(),
        ]);
    }

    public function revenue(): View
    {
        return view('admin.vendors.revenue', [
            'revenue' => $this->revenue->platformOverview(),
        ]);
    }
}
