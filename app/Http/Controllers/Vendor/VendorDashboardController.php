<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorDashboardService;
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
        $vendor = $request->attributes->get('vendor');
        $stats = $this->dashboard->stats($vendor);

        $statCards = [
            ['label' => 'Total Products', 'value' => $stats['total_products'] ?? 0],
            ['label' => 'Active Products', 'value' => $stats['active_products'] ?? 0],
            ['label' => 'Out of Stock', 'value' => $stats['out_of_stock_products'] ?? 0],
            ['label' => 'Low Stock', 'value' => $stats['low_stock_products'] ?? 0],
            ['label' => 'Total Orders', 'value' => $stats['total_orders'] ?? 0],
            ['label' => 'Pending Orders', 'value' => $stats['pending_orders'] ?? 0],
            ['label' => 'Completed', 'value' => $stats['completed_orders'] ?? 0],
            ['label' => 'Revenue (AED)', 'value' => number_format((float) ($stats['revenue'] ?? 0), 2)],
        ];

        return view('vendor.dashboard', compact('stats', 'vendor', 'statCards'));
    }

    public function pending()
    {
        return view('vendor.pending');
    }
}
