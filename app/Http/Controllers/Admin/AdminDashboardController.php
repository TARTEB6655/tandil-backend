<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\Complaint;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\Area;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Widget counts
        $totalUsers = User::count();
        $totalTechnicians = User::where('role', 'technician')->count();
        $totalSupervisors = User::where('role', 'supervisor')->count();
        $activeSubscriptions = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', Carbon::today())
            ->count();
        
        // Visits counts
        $visitsToday = Visit::whereDate('scheduled_date', Carbon::today())->count();
        $visitsThisWeek = Visit::whereBetween('scheduled_date', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count();
        $visitsThisMonth = Visit::whereMonth('scheduled_date', Carbon::now()->month)
            ->whereYear('scheduled_date', Carbon::now()->year)
            ->count();
        $totalVisits = Visit::count();
        
        // Revenue analytics
        $revenueToday = Order::where('order_status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('total_amount');
        
        $revenueThisMonth = Order::where('order_status', 'completed')
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->sum('total_amount');
        
        $totalRevenue = Order::where('order_status', 'completed')
            ->sum('total_amount');
        
        $subscriptionRevenue = Subscription::where('payment_status', 'paid')
            ->sum('amount');
        
        $totalRevenue = $totalRevenue + $subscriptionRevenue;
        
        // Pending reports
        $pendingReports = Report::where('status', 'pending')->count();
        
        // Complaints
        $totalComplaints = Complaint::count();
        $pendingComplaints = Complaint::where('status', 'pending')->count();
        
        // Products sold
        $productsSold = Order::where('order_status', 'completed')
            ->sum('total_amount'); // This should be from order_items, but using total for now
        
        // Active regions
        $activeRegions = Area::count();
        
        // Visits per week (last 8 weeks for better chart)
        $visitsPerWeek = Visit::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%u") as week'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subWeeks(8))
            ->groupBy('week')
            ->orderBy('week', 'asc')
            ->get();

        // Subscription growth (last 6 months)
        $subscriptionGrowth = Subscription::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Active subscriptions by plan
        $subscriptionsByPlan = Subscription::select('plan', DB::raw('COUNT(*) as count'))
            ->where('payment_status', 'paid')
            ->where('end_date', '>=', Carbon::today())
            ->groupBy('plan')
            ->get();

        // Technician performance (top 10 by completed visits)
        $technicianPerformance = User::where('role', 'technician')
            ->whereHas('visits')
            ->withCount(['visits' => function($query) {
                $query->where('status', 'completed');
            }])
            ->orderBy('visits_count', 'desc')
            ->take(10)
            ->get();

        // Product sales analytics (last 6 months)
        $productSales = Order::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('order_status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Recent activity
        $recentOrders = Order::with('user')->orderBy('created_at', 'desc')->take(5)->get();
        $recentSubscriptions = Subscription::with('client')->orderBy('created_at', 'desc')->take(5)->get();
        $recentVisits = Visit::with(['technician', 'subscription.client'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Status breakdowns
        $visitsByStatus = Visit::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $subscriptionsByStatus = Subscription::select('payment_status', DB::raw('COUNT(*) as count'))
            ->groupBy('payment_status')
            ->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalTechnicians',
            'totalSupervisors',
            'activeSubscriptions',
            'visitsToday',
            'visitsThisWeek',
            'visitsThisMonth',
            'totalVisits',
            'revenueToday',
            'revenueThisMonth',
            'totalRevenue',
            'pendingReports',
            'totalComplaints',
            'pendingComplaints',
            'productsSold',
            'activeRegions',
            'visitsPerWeek',
            'subscriptionGrowth',
            'subscriptionsByPlan',
            'technicianPerformance',
            'productSales',
            'recentOrders',
            'recentSubscriptions',
            'recentVisits',
            'visitsByStatus',
            'subscriptionsByStatus'
        ));
    }
}
