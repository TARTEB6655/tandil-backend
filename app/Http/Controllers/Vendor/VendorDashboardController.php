<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorDashboardService;
use App\Support\VendorContext;
use Illuminate\Http\Request;

class VendorDashboardController extends Controller
{
    public function __construct(
        private readonly VendorDashboardService $dashboard
    ) {
        $this->middleware(['auth', 'role:vendor']);
        $this->middleware('vendor.approved')->only('index');
    }

    public function index(Request $request)
    {
        $vendor = $request->attributes->get('vendor') ?? VendorContext::requireApprovedVendor($request->user());
        $stats = $this->dashboard->stats($vendor);

        return view('vendor.dashboard', compact('stats', 'vendor'));
    }

    public function pending()
    {
        return view('vendor.pending');
    }
}
