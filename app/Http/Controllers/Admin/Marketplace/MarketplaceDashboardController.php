<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Controller;
use App\Enums\VendorStatus;
use App\Models\SupportChatSession;
use App\Models\Vendor;
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
        $activeLiveChats = SupportChatSession::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->whereHas('user', fn ($q) => $q->where('role', 'vendor'))
            ->count();
        $recentVendorRequests = Vendor::with(['profile', 'user'])
            ->whereIn('status', [VendorStatus::Pending->value, VendorStatus::UnderReview->value])
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.marketplace.dashboard', compact(
            'overview',
            'topVendors',
            'activeLiveChats',
            'recentVendorRequests'
        ));
    }
}
