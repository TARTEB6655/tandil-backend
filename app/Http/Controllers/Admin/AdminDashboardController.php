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
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        $driver = DB::connection()->getDriverName();
        $dateFormat = $driver === 'sqlite' 
            ? DB::raw("strftime('%Y-%m', created_at) as month")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month');
        
        $revenueByMonth = Order::select(
                $dateFormat,
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
        $driver = DB::connection()->getDriverName();
        $weekFormat = $driver === 'sqlite'
            ? DB::raw("strftime('%Y-%W', created_at) as week")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%u") as week');
        
        $visitsPerWeek = Visit::select(
                $weekFormat,
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subWeeks(8))
            ->groupBy('week')
            ->orderBy('week', 'asc')
            ->get();

        // Subscription growth (last 6 months)
        $driver = DB::connection()->getDriverName();
        $monthFormat = $driver === 'sqlite'
            ? DB::raw("strftime('%Y-%m', created_at) as month")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month');
        
        $subscriptionGrowth = Subscription::select(
                $monthFormat,
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
            ->whereHas('visits')
            ->withCount('visits')
            ->orderBy('visits_count', 'desc')
            ->take(10)
            ->get();

        // Product sales analytics (last 6 months) - using order items
        $driver = DB::connection()->getDriverName();
        $monthFormat = $driver === 'sqlite'
            ? DB::raw("strftime('%Y-%m', created_at) as month")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month');
        
        $productSales = OrderItem::select(
                $monthFormat,
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

        // Statistics with time range (for new statistics section)
        $timeRange = $request->get('stats_range', 'monthly'); // daily, weekly, monthly, yearly
        $stats = $this->getStatisticsByTimeRange($timeRange);

        // Get all active users with their roles
        $allActiveUsers = User::where('status', 'active')
            ->with('roles')
            ->select('id', 'name', 'email', 'phone', 'role', 'status', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(function($user) {
                // Get role from Spatie Permission or fallback to role field
                $spatieRole = $user->roles->first();
                $roleName = $spatieRole ? $spatieRole->name : ($user->role ?? 'No Role');
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $roleName,
                    'role_id' => $spatieRole ? $spatieRole->id : null,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                ];
            });

        // Roles with assigned users - Get users both from Spatie roles and role field
        $rolesWithUsers = \Spatie\Permission\Models\Role::orderBy('name')->get()->map(function($role) {
            // Get users from Spatie Permission pivot table
            $spatieUsers = User::whereHas('roles', function($q) use ($role) {
                $q->where('roles.id', $role->id);
            })
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'phone', 'role', 'status')
            ->orderBy('name')
            ->get();
            
            // Also get users that have this role in the role field but might not be in Spatie pivot
            $usersFromRoleField = User::where('status', 'active')
                ->where('role', $role->name)
                ->whereDoesntHave('roles', function($q) use ($role) {
                    $q->where('roles.id', $role->id);
                })
                ->select('id', 'name', 'email', 'phone', 'role', 'status')
                ->orderBy('name')
                ->get();
            
            // Merge Spatie role users with role field users and remove duplicates
            $allUsers = $spatieUsers->merge($usersFromRoleField)->unique('id');
            
            // Create a collection-like object that the view can use
            $roleObj = new \stdClass();
            $roleObj->id = $role->id;
            $roleObj->name = $role->name;
            $roleObj->description = $role->description ?? null;
            $roleObj->users_count = $allUsers->count();
            $roleObj->users = $allUsers;
            
            return $roleObj;
        });

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
            'areaPerformance',
            // Users and roles
            'allActiveUsers',
            'rolesWithUsers',
            // Statistics
            'stats',
            'timeRange'
        ));
    }

    /**
     * Get statistics by time range
     */
    private function getStatisticsByTimeRange($range = 'monthly')
    {
        $now = Carbon::now();
        $startDate = null;
        $previousStartDate = null;
        $previousEndDate = null;

        switch ($range) {
            case 'daily':
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                $previousStartDate = $now->copy()->subDay()->startOfDay();
                $previousEndDate = $now->copy()->subDay()->endOfDay();
                break;
            case 'weekly':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                $previousStartDate = $now->copy()->subWeek()->startOfWeek();
                $previousEndDate = $now->copy()->subWeek()->endOfWeek();
                break;
            case 'monthly':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                $previousStartDate = $now->copy()->subMonth()->startOfMonth();
                $previousEndDate = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'yearly':
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
                $previousStartDate = $now->copy()->subYear()->startOfYear();
                $previousEndDate = $now->copy()->subYear()->endOfYear();
                break;
            default:
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                $previousStartDate = $now->copy()->subMonth()->startOfMonth();
                $previousEndDate = $now->copy()->subMonth()->endOfMonth();
        }

        // Customers (users with client role)
        $customersCurrent = User::where('role', 'client')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $customersPrevious = User::where('role', 'client')
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();
        $customersGrowth = $this->calculateGrowth($customersCurrent, $customersPrevious);

        // Technicians
        $techniciansCurrent = User::where('role', 'technician')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $techniciansPrevious = User::where('role', 'technician')
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();
        $techniciansGrowth = $this->calculateGrowth($techniciansCurrent, $techniciansPrevious);

        // Employees/Staff (from Employee model or users with hr role)
        $employeesCurrent = Employee::whereBetween('created_at', [$startDate, $endDate])->count() 
            + User::where('role', 'hr')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
        $employeesPrevious = Employee::whereBetween('created_at', [$previousStartDate, $previousEndDate])->count()
            + User::where('role', 'hr')
                ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
                ->count();
        $employeesGrowth = $this->calculateGrowth($employeesCurrent, $employeesPrevious);

        return [
            'customers' => [
                'current' => $customersCurrent,
                'previous' => $customersPrevious,
                'growth' => $customersGrowth,
            ],
            'technicians' => [
                'current' => $techniciansCurrent,
                'previous' => $techniciansPrevious,
                'growth' => $techniciansGrowth,
            ],
            'employees' => [
                'current' => $employeesCurrent,
                'previous' => $employeesPrevious,
                'growth' => $employeesGrowth,
            ],
            'range' => $range,
            'period_label' => $this->getPeriodLabel($range),
        ];
    }

    /**
     * Calculate growth percentage
     */
    private function calculateGrowth($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get period label
     */
    private function getPeriodLabel($range)
    {
        switch ($range) {
            case 'daily':
                return 'Today';
            case 'weekly':
                return 'This Week';
            case 'monthly':
                return 'This Month';
            case 'yearly':
                return 'This Year';
            default:
                return 'This Month';
        }
    }
}
