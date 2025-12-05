<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Complaint;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\Area;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Handle search - redirect to users page if search query exists
        if ($request->has('search') && !empty($request->search)) {
            return redirect()->route('admin.users.index', ['search' => $request->search])
                ->with('info', 'Search results for: ' . $request->search);
        }
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
        
        // E-commerce Revenue analytics
        $revenueToday = Order::where('payment_status', 'paid')
            ->whereDate('created_at', Carbon::today())
            ->sum('total_amount');
        
        $revenueThisWeek = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('total_amount');
        
        $revenueThisMonth = Order::where('payment_status', 'paid')
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->sum('total_amount');
        
        $totalRevenue = Order::where('payment_status', 'paid')
            ->sum('total_amount');
        
        $subscriptionRevenue = Subscription::where('payment_status', 'paid')
            ->sum('amount');
        
        $totalRevenue = $totalRevenue + $subscriptionRevenue;
        
        // E-commerce Statistics
        $totalOrders = Order::count();
        $pendingOrders = Order::where('order_status', 'pending')->count();
        $processingOrders = Order::where('order_status', 'processing')->count();
        $deliveredOrders = Order::where('order_status', 'delivered')->count();
        
        $paidOrders = Order::where('payment_status', 'paid')->count();
        $pendingPayments = Order::where('payment_status', 'pending')->count();
        $failedPayments = Order::where('payment_status', 'failed')->count();
        
        $totalProducts = Product::count();
        $lowStockProducts = Product::where('stock', '<', 10)->count();
        $outOfStockProducts = Product::where('stock', 0)->count();
        
        // Top selling products
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->with('product')
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->take(10)
            ->get();
        
        // Orders by status
        $ordersByStatus = Order::select('order_status', DB::raw('COUNT(*) as count'))
            ->groupBy('order_status')
            ->get();
        
        // Orders by payment status
        $ordersByPaymentStatus = Order::select('payment_status', DB::raw('COUNT(*) as count'))
            ->groupBy('payment_status')
            ->get();
        
        // Revenue by month (last 6 months)
        $revenueByMonth = Order::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();
        
        // Recent orders with items
        $recentOrders = Order::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        // Top customers (by order value)
        $topCustomers = Order::select('user_id', DB::raw('SUM(total_amount) as total_spent'), DB::raw('COUNT(*) as order_count'))
            ->with('user')
            ->where('payment_status', 'paid')
            ->groupBy('user_id')
            ->orderBy('total_spent', 'desc')
            ->take(10)
            ->get();
        
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
        
        // Area performance (visits by area)
        $areaPerformance = Area::withCount(['visits' => function($query) {
                $query->whereNotNull('area_id');
            }])
            ->orderBy('visits_count', 'desc')
            ->take(10)
            ->get();
        
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

        // Technician performance (top 10 by all visits, not just completed)
        $technicianPerformance = User::where('role', 'technician')
            ->withCount('visits')
            ->having('visits_count', '>', 0)
            ->orderBy('visits_count', 'desc')
            ->take(10)
            ->get();

        // Product sales analytics (last 6 months) - using order items
        $productSales = OrderItem::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(subtotal) as revenue'),
                DB::raw('SUM(quantity) as items_sold')
            )
            ->whereHas('order', function($query) {
                $query->where('payment_status', 'paid');
            })
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();
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
            'revenueThisWeek',
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
            'subscriptionsByStatus',
            // E-commerce stats
            'totalOrders',
            'pendingOrders',
            'processingOrders',
            'deliveredOrders',
            'paidOrders',
            'pendingPayments',
            'failedPayments',
            'totalProducts',
            'lowStockProducts',
            'outOfStockProducts',
            'topProducts',
            'ordersByStatus',
            'ordersByPaymentStatus',
            'revenueByMonth',
            'topCustomers',
            'areaPerformance'
        ));
    }
}
