<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\Vendor\AdminMarketplaceAnalyticsService;
use Illuminate\Http\Request;

class MarketplaceDashboardController extends Controller
{
    public function __construct(
        private readonly AdminMarketplaceAnalyticsService $analytics
    ) {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $overview = $this->analytics->overview();
        $topVendors = $this->analytics->topVendorsByRevenue(10);

        return view('admin.marketplace.dashboard', compact('overview', 'topVendors'));
    }
}
