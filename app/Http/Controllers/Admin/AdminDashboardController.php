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
use App\Models\AdminReport;
use App\Models\Tip;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Package;
use App\Models\Employee;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\ProfilePictureUploadService;
use App\Services\ImageCompressionService;

class AdminDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        // Handle search - redirect to users page if search query exists
        if ($request->has('search') && !empty($request->search)) {
            return redirect()->route('admin.users.index', ['search' => $request->search])
                ->with('info', 'Search results for: ' . $request->search);
        }

        try {
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

        $totalCategories = Category::count();
        $totalServices = \App\Models\Service::count();
        $activeCategories = Category::where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })->count();
        
        // Top selling products (orderByRaw for MySQL ONLY_FULL_GROUP_BY)
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(subtotal) as total_revenue'))
            ->with('product')
            ->groupBy('product_id')
            ->orderByRaw('SUM(quantity) DESC')
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
        $isSqlite = in_array($driver, ['sqlite', 'sqlite3'], true);
        $dateFormat = $isSqlite
            ? DB::raw("strftime('%Y-%m', created_at) as month")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month');
        $groupByMonth = $isSqlite
            ? DB::raw("strftime('%Y-%m', created_at)")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m")');
        
        $revenueByMonth = Order::select(
                $dateFormat,
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy($groupByMonth)
            ->orderByRaw($isSqlite ? "strftime('%Y-%m', created_at) ASC" : "DATE_FORMAT(created_at, '%Y-%m') ASC")
            ->get();
        
        // Recent orders with items
        $recentOrders = Order::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        // Top customers (by order value) (orderByRaw for MySQL ONLY_FULL_GROUP_BY)
        $topCustomers = Order::select('user_id', DB::raw('SUM(total_amount) as total_spent'), DB::raw('COUNT(*) as order_count'))
            ->with('user')
            ->where('payment_status', 'paid')
            ->groupBy('user_id')
            ->orderByRaw('SUM(total_amount) DESC')
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
        $isSqlite = in_array($driver, ['sqlite', 'sqlite3'], true);
        $weekFormat = $isSqlite
            ? DB::raw("strftime('%Y-%W', created_at) as week")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%u") as week');
        $groupByWeek = $isSqlite
            ? DB::raw("strftime('%Y-%W', created_at)")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%u")');
        
        $visitsPerWeek = Visit::select(
                $weekFormat,
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subWeeks(8))
            ->groupBy($groupByWeek)
            ->orderByRaw($isSqlite ? "strftime('%Y-%W', created_at) ASC" : "DATE_FORMAT(created_at, '%Y-%u') ASC")
            ->get();

        // Subscription growth (last 6 months)
        $driver = DB::connection()->getDriverName();
        $isSqlite = in_array($driver, ['sqlite', 'sqlite3'], true);
        $monthFormat = $isSqlite
            ? DB::raw("strftime('%Y-%m', created_at) as month")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month');
        $groupByMonthSub = $isSqlite
            ? DB::raw("strftime('%Y-%m', created_at)")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m")');
        
        $subscriptionGrowth = Subscription::select(
                $monthFormat,
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy($groupByMonthSub)
            ->orderByRaw($isSqlite ? "strftime('%Y-%m', created_at) ASC" : "DATE_FORMAT(created_at, '%Y-%m') ASC")
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
        $isSqlite = in_array($driver, ['sqlite', 'sqlite3'], true);
        $monthFormat = $isSqlite
            ? DB::raw("strftime('%Y-%m', created_at) as month")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month');
        $groupByMonthProd = $isSqlite
            ? DB::raw("strftime('%Y-%m', created_at)")
            : DB::raw('DATE_FORMAT(created_at, "%Y-%m")');
        
        $productSales = OrderItem::select(
                $monthFormat,
                DB::raw('SUM(subtotal) as revenue'),
                DB::raw('SUM(quantity) as items_sold')
            )
            ->whereHas('order', function($query) {
                $query->where('payment_status', 'paid');
            })
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy($groupByMonthProd)
            ->orderByRaw($isSqlite ? "strftime('%Y-%m', created_at) ASC" : "DATE_FORMAT(created_at, '%Y-%m') ASC")
            ->get();
        $recentSubscriptions = Subscription::with('client')->orderBy('created_at', 'desc')->take(5)->get();
        $recentVisits = Visit::with(['technician', 'subscription.client'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Recent activities for dashboard (only 5; "View All" goes to dedicated page)
        $recentActivities = $this->getRecentActivitiesForDashboard(5);

        // Recent tips (latest 5 for dashboard)
        $recentTips = Tip::with('creator')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $totalTips = Tip::count();
        $totalBanners = Banner::count();
        $activeBannersCount = Banner::where('is_active', true)->count();
        $totalPackages = Package::count();
        $totalAdminReports = AdminReport::count();

        // Orders today (for "New Orders" summary card)
        $ordersToday = Order::whereDate('created_at', Carbon::today())->count();

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

        // Get active users with pagination (5 per page)
        $activeUsersQuery = User::where('status', 'active')
            ->with('roles')
            ->select('id', 'name', 'email', 'phone', 'role', 'status', 'created_at')
            ->orderBy('name');
        
        $allActiveUsersPaginated = $activeUsersQuery->paginate(5, ['*'], 'users_page');
        
        $allActiveUsers = $allActiveUsersPaginated->map(function($user) {
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
            'recentActivities',
            'recentTips',
            'totalTips',
            'totalBanners',
            'activeBannersCount',
            'totalPackages',
            'totalAdminReports',
            'totalUsers',
            'totalTechnicians',
            'allActiveUsersPaginated',
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
            'ordersToday',
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
            'totalCategories',
            'totalServices',
            'activeCategories',
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
        } catch (\Throwable $e) {
            Log::error('Admin dashboard index error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Dedicated page: all recent activities with pagination
     * GET /admin/recent-activities
     */
    public function recentActivitiesPage(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15;
        $allActivities = $this->getRecentActivitiesForDashboard(200);
        $total = count($allActivities);
        $paginator = new LengthAwarePaginator(
            array_slice($allActivities, ($page - 1) * $perPage, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.recent-activities.index', [
            'activities' => $paginator,
        ]);
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

    /**
     * Get dashboard statistics for API (Mobile App)
     * GET /api/admin/dashboard/statistics
     */
    public function statistics(Request $request)
    {
        $now = Carbon::now();

        // Calculate date ranges
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();

        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
        $lastWeekEnd = $now->copy()->subWeek()->endOfWeek();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $yearStart = $now->copy()->startOfYear();
        $yearEnd = $now->copy()->endOfYear();
        $lastYearStart = $now->copy()->subYear()->startOfYear();
        $lastYearEnd = $now->copy()->subYear()->endOfYear();

        // Customers Statistics
        $customersTotal = User::where('role', 'client')->count();
        $customersDaily = User::where('role', 'client')
            ->whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $customersWeekly = User::where('role', 'client')
            ->whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $customersMonthly = User::where('role', 'client')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $customersYearly = User::where('role', 'client')
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count();

        $customersDailyPrev = User::where('role', 'client')
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
        $customersWeeklyPrev = User::where('role', 'client')
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $customersMonthlyPrev = User::where('role', 'client')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $customersYearlyPrev = User::where('role', 'client')
            ->whereBetween('created_at', [$lastYearStart, $lastYearEnd])->count();

        // Technicians Statistics
        $techniciansTotal = User::where('role', 'technician')->count();
        $techniciansDaily = User::where('role', 'technician')
            ->whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $techniciansWeekly = User::where('role', 'technician')
            ->whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $techniciansMonthly = User::where('role', 'technician')
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $techniciansYearly = User::where('role', 'technician')
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count();

        $techniciansDailyPrev = User::where('role', 'technician')
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
        $techniciansWeeklyPrev = User::where('role', 'technician')
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $techniciansMonthlyPrev = User::where('role', 'technician')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $techniciansYearlyPrev = User::where('role', 'technician')
            ->whereBetween('created_at', [$lastYearStart, $lastYearEnd])->count();

        // Employees Statistics (Technicians + Supervisors + Area Managers + HR)
        $employeesTotal = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])->count()
            + Employee::count();
        
        $employeesDaily = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$todayStart, $todayEnd])->count()
            + Employee::whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $employeesWeekly = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])->count()
            + Employee::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $employeesMonthly = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count()
            + Employee::whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $employeesYearly = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count()
            + Employee::whereBetween('created_at', [$yearStart, $yearEnd])->count();

        $employeesDailyPrev = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count()
            + Employee::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
        $employeesWeeklyPrev = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count()
            + Employee::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $employeesMonthlyPrev = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count()
            + Employee::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $employeesYearlyPrev = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereBetween('created_at', [$lastYearStart, $lastYearEnd])->count()
            + Employee::whereBetween('created_at', [$lastYearStart, $lastYearEnd])->count();

        // Total Users Statistics (All users regardless of role)
        $totalUsersTotal = User::count();
        $totalUsersDaily = User::whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $totalUsersWeekly = User::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $totalUsersMonthly = User::whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $totalUsersYearly = User::whereBetween('created_at', [$yearStart, $yearEnd])->count();

        $totalUsersDailyPrev = User::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
        $totalUsersWeeklyPrev = User::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $totalUsersMonthlyPrev = User::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $totalUsersYearlyPrev = User::whereBetween('created_at', [$lastYearStart, $lastYearEnd])->count();

        // Active Subscriptions Statistics
        $subscriptionsTotal = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now)
            ->count();
        $subscriptionsDaily = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now)
            ->whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $subscriptionsWeekly = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now)
            ->whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $subscriptionsMonthly = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now)
            ->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $subscriptionsYearly = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now)
            ->whereBetween('created_at', [$yearStart, $yearEnd])->count();

        $subscriptionsDailyPrev = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now->copy()->subDay())
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
        $subscriptionsWeeklyPrev = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now->copy()->subWeek())
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $subscriptionsMonthlyPrev = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now->copy()->subMonth())
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        $subscriptionsYearlyPrev = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', $now->copy()->subYear())
            ->whereBetween('created_at', [$lastYearStart, $lastYearEnd])->count();

        // Monthly Revenue Statistics (Orders + Subscriptions)
        $revenueTotal = Order::where('payment_status', 'paid')->sum('total_amount')
            + Subscription::where('payment_status', 'paid')->sum('amount');
        
        $revenueDaily = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->sum('amount');
        
        $revenueWeekly = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->sum('amount');
        
        $revenueMonthly = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');
        
        $revenueYearly = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$yearStart, $yearEnd])
                ->sum('amount');

        $revenueDailyPrev = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
                ->sum('amount');
        
        $revenueWeeklyPrev = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->sum('amount');
        
        $revenueMonthlyPrev = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('amount');
        
        $revenueYearlyPrev = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$lastYearStart, $lastYearEnd])
            ->sum('total_amount')
            + Subscription::where('payment_status', 'paid')
                ->whereBetween('created_at', [$lastYearStart, $lastYearEnd])
                ->sum('amount');

        // Format growth as string with + or - sign
        $formatGrowth = function($current, $previous) {
            if ($previous == 0) {
                return $current > 0 ? '+' . $current : '0';
            }
            $growth = round((($current - $previous) / $previous) * 100, 0);
            return ($growth >= 0 ? '+' : '') . $growth;
        };

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => [
                    'total' => $totalUsersTotal,
                    'daily' => $totalUsersDaily,
                    'weekly' => $totalUsersWeekly,
                    'monthly' => $totalUsersMonthly,
                    'yearly' => $totalUsersYearly,
                    'growth' => [
                        'daily' => $formatGrowth($totalUsersDaily, $totalUsersDailyPrev),
                        'weekly' => $formatGrowth($totalUsersWeekly, $totalUsersWeeklyPrev),
                        'monthly' => $formatGrowth($totalUsersMonthly, $totalUsersMonthlyPrev),
                        'yearly' => $formatGrowth($totalUsersYearly, $totalUsersYearlyPrev),
                    ],
                ],
                'active_subscriptions' => [
                    'total' => $subscriptionsTotal,
                    'daily' => $subscriptionsDaily,
                    'weekly' => $subscriptionsWeekly,
                    'monthly' => $subscriptionsMonthly,
                    'yearly' => $subscriptionsYearly,
                    'growth' => [
                        'daily' => $formatGrowth($subscriptionsDaily, $subscriptionsDailyPrev),
                        'weekly' => $formatGrowth($subscriptionsWeekly, $subscriptionsWeeklyPrev),
                        'monthly' => $formatGrowth($subscriptionsMonthly, $subscriptionsMonthlyPrev),
                        'yearly' => $formatGrowth($subscriptionsYearly, $subscriptionsYearlyPrev),
                    ],
                ],
                'monthly_revenue' => [
                    'total' => round($revenueTotal, 2),
                    'daily' => round($revenueDaily, 2),
                    'weekly' => round($revenueWeekly, 2),
                    'monthly' => round($revenueMonthly, 2),
                    'yearly' => round($revenueYearly, 2),
                    'growth' => [
                        'daily' => $formatGrowth($revenueDaily, $revenueDailyPrev),
                        'weekly' => $formatGrowth($revenueWeekly, $revenueWeeklyPrev),
                        'monthly' => $formatGrowth($revenueMonthly, $revenueMonthlyPrev),
                        'yearly' => $formatGrowth($revenueYearly, $revenueYearlyPrev),
                    ],
                ],
                'customers' => [
                    'total' => $customersTotal,
                    'daily' => $customersDaily,
                    'weekly' => $customersWeekly,
                    'monthly' => $customersMonthly,
                    'yearly' => $customersYearly,
                    'growth' => [
                        'daily' => $formatGrowth($customersDaily, $customersDailyPrev),
                        'weekly' => $formatGrowth($customersWeekly, $customersWeeklyPrev),
                        'monthly' => $formatGrowth($customersMonthly, $customersMonthlyPrev),
                        'yearly' => $formatGrowth($customersYearly, $customersYearlyPrev),
                    ],
                ],
                'technicians' => [
                    'total' => $techniciansTotal,
                    'daily' => $techniciansDaily,
                    'weekly' => $techniciansWeekly,
                    'monthly' => $techniciansMonthly,
                    'yearly' => $techniciansYearly,
                    'growth' => [
                        'daily' => $formatGrowth($techniciansDaily, $techniciansDailyPrev),
                        'weekly' => $formatGrowth($techniciansWeekly, $techniciansWeeklyPrev),
                        'monthly' => $formatGrowth($techniciansMonthly, $techniciansMonthlyPrev),
                        'yearly' => $formatGrowth($techniciansYearly, $techniciansYearlyPrev),
                    ],
                ],
                'employees' => [
                    'total' => $employeesTotal,
                    'daily' => $employeesDaily,
                    'weekly' => $employeesWeekly,
                    'monthly' => $employeesMonthly,
                    'yearly' => $employeesYearly,
                    'growth' => [
                        'daily' => $formatGrowth($employeesDaily, $employeesDailyPrev),
                        'weekly' => $formatGrowth($employeesWeekly, $employeesWeeklyPrev),
                        'monthly' => $formatGrowth($employeesMonthly, $employeesMonthlyPrev),
                        'yearly' => $formatGrowth($employeesYearly, $employeesYearlyPrev),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Build recent activities array (shared by web dashboard and API).
     * Returns subscriptions, customers, inventory alerts, orders, visits sorted by date.
     */
    protected function getRecentActivitiesForDashboard(int $limit = 20): array
    {
        $activities = [];

        // 1. Recent Subscriptions (paid subscriptions)
        $recentSubscriptions = Subscription::where('payment_status', 'paid')
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentSubscriptions as $subscription) {
            $planNames = [
                '1_month' => '1-month',
                '3_month' => '3-month',
                '6_month' => '6-month',
                '12_month' => '12-month',
            ];
            $planName = $planNames[$subscription->plan] ?? $subscription->plan;
            
            $activities[] = [
                'type' => 'subscription',
                'icon_type' => 'success', // Green checkmark icon
                'description' => "New {$planName} subscription by " . ($subscription->client->name ?? 'Unknown'),
                'timestamp' => $subscription->created_at->diffForHumans(),
                'created_at' => $subscription->created_at->toISOString(),
                'related_id' => $subscription->id,
                'related_type' => 'subscription',
            ];
        }

        // 2. New Customer Registrations
        $recentCustomers = User::where('role', 'client')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentCustomers as $customer) {
            $activities[] = [
                'type' => 'customer',
                'icon_type' => 'user_add', // User icon with plus sign
                'description' => "New customer registered - {$customer->name}",
                'timestamp' => $customer->created_at->diffForHumans(),
                'created_at' => $customer->created_at->toISOString(),
                'related_id' => $customer->id,
                'related_type' => 'user',
            ];
        }

        // 3. Low Inventory Alerts
        $lowStockProducts = Product::where('stock', '<', 10)
            ->where('stock', '>', 0)
            ->orderBy('stock', 'asc')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($lowStockProducts as $product) {
            $activities[] = [
                'type' => 'inventory',
                'icon_type' => 'warning', // Yellow/orange warning icon
                'description' => "Low inventory alert: {$product->name}",
                'timestamp' => $product->updated_at->diffForHumans(),
                'created_at' => $product->updated_at->toISOString(),
                'related_id' => $product->id,
                'related_type' => 'product',
                'stock' => $product->stock, // Additional info for inventory alerts
            ];
        }

        // 4. Out of Stock Alerts
        $outOfStockProducts = Product::where('stock', 0)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($outOfStockProducts as $product) {
            $activities[] = [
                'type' => 'inventory',
                'icon_type' => 'error', // Red error icon
                'description' => "Out of stock: {$product->name}",
                'timestamp' => $product->updated_at->diffForHumans(),
                'created_at' => $product->updated_at->toISOString(),
                'related_id' => $product->id,
                'related_type' => 'product',
                'stock' => 0,
            ];
        }

        // 5. Recent Orders
        $recentOrders = Order::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentOrders as $order) {
            $activities[] = [
                'type' => 'order',
                'icon_type' => 'order', // Shopping cart/order icon
                'description' => "New order #{$order->id} by " . ($order->user->name ?? 'Unknown'),
                'timestamp' => $order->created_at->diffForHumans(),
                'created_at' => $order->created_at->toISOString(),
                'related_id' => $order->id,
                'related_type' => 'order',
                'amount' => (float) $order->total_amount,
                'status' => $order->order_status,
            ];
        }

        // Sort all activities by created_at (most recent first)
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($activities, 0, $limit);
    }

    /**
     * Get recent activities for React Native dashboard (API)
     */
    public function recentActivities(Request $request)
    {
        $limit = (int) $request->input('limit', 20);
        $activities = $this->getRecentActivitiesForDashboard($limit);

        return response()->json([
            'success' => true,
            'data' => $activities,
            'total' => count($activities),
        ]);
    }

    /**
     * Get quick overview for React Native dashboard
     * Returns counts for new orders, support tickets, and other quick stats
     * with percentage changes vs previous period
     */
    public function quickOverview(Request $request)
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        
        // Previous period (same period last time)
        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();
        
        // This week
        $thisWeekStart = $now->copy()->startOfWeek();
        $thisWeekEnd = $now->copy()->endOfWeek();
        
        // Last week
        $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
        $lastWeekEnd = $now->copy()->subWeek()->endOfWeek();

        // Helper function to calculate percentage change
        $calculateGrowth = function($current, $previous) {
            if ($previous == 0) {
                return $current > 0 ? '+100' : '0';
            }
            $change = (($current - $previous) / $previous) * 100;
            return $change >= 0 ? '+' . round($change, 0) : round($change, 0);
        };

        // 1. New Orders (today)
        $newOrdersToday = Order::whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $newOrdersYesterday = Order::whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->count();
        $newOrdersGrowth = $calculateGrowth($newOrdersToday, $newOrdersYesterday);

        // 2. Support Tickets (open and in_progress – submitted by clients/technicians via /api/support/tickets)
        $supportTicketsOpen = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
        $supportTicketsYesterday = SupportTicket::whereIn('status', ['open', 'in_progress'])
            ->where('updated_at', '<', $todayStart)
            ->where('updated_at', '>=', $yesterdayStart)
            ->count();
        $supportTicketsGrowth = $calculateGrowth($supportTicketsOpen, $supportTicketsYesterday > 0 ? $supportTicketsYesterday : $supportTicketsOpen);

        // 3. New Customers (today)
        $newCustomersToday = User::where('role', 'client')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();
        $newCustomersYesterday = User::where('role', 'client')
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->count();
        $newCustomersGrowth = $calculateGrowth($newCustomersToday, $newCustomersYesterday);

        // 4. New Subscriptions (today)
        $newSubscriptionsToday = Subscription::where('payment_status', 'paid')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();
        $newSubscriptionsYesterday = Subscription::where('payment_status', 'paid')
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->count();
        $newSubscriptionsGrowth = $calculateGrowth($newSubscriptionsToday, $newSubscriptionsYesterday);

        // 5. Revenue Today
        $revenueToday = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('total_amount');
        $revenueYesterday = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->sum('total_amount');
        $revenueGrowth = $calculateGrowth($revenueToday, $revenueYesterday);

        // 6. Pending Visits (scheduled but not completed)
        $pendingVisits = Visit::where('status', '!=', 'completed')
            ->where('scheduled_date', '>=', Carbon::today())
            ->count();
        $pendingVisitsYesterday = Visit::where('status', '!=', 'completed')
            ->where('scheduled_date', '>=', Carbon::yesterday())
            ->where('scheduled_date', '<', Carbon::today())
            ->count();
        $pendingVisitsGrowth = $calculateGrowth($pendingVisits, $pendingVisitsYesterday);

        // 7. Pending Reports
        $pendingReports = Report::where('status', 'pending')->count();
        $pendingReportsYesterday = Report::where('status', 'pending')
            ->where('updated_at', '<', $todayStart)
            ->where('updated_at', '>=', $yesterdayStart)
            ->count();
        // For pending reports, compare current pending count vs yesterday's pending count
        $pendingReportsGrowth = $calculateGrowth($pendingReports, $pendingReportsYesterday > 0 ? $pendingReportsYesterday : $pendingReports);

        return response()->json([
            'success' => true,
            'data' => [
                'pending_reports' => [
                    'count' => $pendingReports,
                    'growth' => $pendingReportsGrowth,
                    'label' => 'Pending Reports',
                ],
                'new_orders' => [
                    'count' => $newOrdersToday,
                    'growth' => $newOrdersGrowth,
                    'label' => 'New Orders',
                ],
                'support_tickets' => [
                    'count' => $supportTicketsOpen,
                    'growth' => $supportTicketsGrowth,
                    'label' => 'Support Tickets',
                ],
                'new_customers' => [
                    'count' => $newCustomersToday,
                    'growth' => $newCustomersGrowth,
                    'label' => 'New Customers',
                ],
                'new_subscriptions' => [
                    'count' => $newSubscriptionsToday,
                    'growth' => $newSubscriptionsGrowth,
                    'label' => 'New Subscriptions',
                ],
                'revenue_today' => [
                    'amount' => round($revenueToday, 2),
                    'growth' => $revenueGrowth,
                    'label' => 'Revenue Today',
                ],
                'pending_visits' => [
                    'count' => $pendingVisits,
                    'growth' => $pendingVisitsGrowth,
                    'label' => 'Pending Visits',
                ],
            ],
        ]);
    }

    /**
     * Get admin user profile with formatted ID for React Native dashboard
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->adminProfileData($request->user()),
        ]);
    }

    /**
     * PUT/POST /api/admin/dashboard/profile - Update admin/HR profile (name, email, phone, profile_picture).
     * Body: form-data only. No password fields. Use POST with multipart when uploading profile_picture.
     */
    public function updateProfile(Request $request)
    {
        $contentType = (string) $request->header('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            return response()->json([
                'success' => false,
                'message' => 'Use form-data (multipart/form-data or x-www-form-urlencoded), not JSON. Required for profile_picture upload.',
            ], 415);
        }

        $user = $request->user();

        $profileFile = $request->file('profile_picture');
        if (is_array($profileFile)) {
            $profileFile = $profileFile[0] ?? null;
        }
        $storedFromPut = null;
        if (! $profileFile && $request->isMethod('PUT') && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $storedFromPut = ProfilePictureUploadService::storeFromMultipartPut($request);
        }

        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
        ];
        if ($profileFile || $storedFromPut) {
            $rules['profile_picture'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp';
        }
        $validator = Validator::make(array_merge($request->all(), ['profile_picture' => $profileFile]), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->input('name');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email');
        }
        if ($request->has('phone')) {
            $user->phone = $request->input('phone') ?: null;
        }
        if ($profileFile && is_object($profileFile) && method_exists($profileFile, 'store')) {
            $stored = $profileFile->store('profiles', 'public');
            $user->profile_picture = $stored;
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        } elseif ($storedFromPut) {
            $user->profile_picture = $storedFromPut;
            ImageCompressionService::compressIfNeededFromPublicPath($storedFromPut);
        }
        $user->save();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $this->adminProfileData($user),
        ]);
    }

    /**
     * Build profile response data for admin (GET and update response).
     */
    private function adminProfileData(User $user): array
    {
        $rolePrefix = strtoupper(substr($user->role ?? 'USER', 0, 5));
        $formattedId = $rolePrefix . '-' . str_pad($user->id, 4, '0', STR_PAD_LEFT);
        $roleDisplayNames = [
            'admin' => 'Executive Management',
            'supervisor' => 'Supervisor',
            'technician' => 'Technician',
            'client' => 'Customer',
            'hr' => 'HR Manager',
            'area_manager' => 'Area Manager',
        ];
        $roleDisplayName = $roleDisplayNames[$user->role] ?? ucfirst($user->role ?? 'User');
        $hour = Carbon::now()->hour;
        $greeting = 'Good morning!';
        if ($hour >= 12 && $hour < 17) {
            $greeting = 'Good afternoon!';
        } elseif ($hour >= 17 && $hour < 21) {
            $greeting = 'Good evening!';
        } elseif ($hour >= 21 || $hour < 5) {
            $greeting = 'Good night!';
        }
        return [
            'id' => $user->id,
            'formatted_id' => $formattedId,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'role_display_name' => $roleDisplayName,
            'status' => $user->status,
            'greeting' => $greeting,
            'profile_picture' => $user->profile_picture,
            'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture) ?? $user->profile_picture_url,
            'created_at' => $user->created_at->toISOString(),
        ];
    }

    /**
     * Get top selling products for admin dashboard (sales count + revenue).
     * GET /api/admin/dashboard/top-selling-products
     * Query: limit (default 10, max 50).
     * Uses join on orders so only paid orders are included; response includes revenue_display (e.g. "22K") for UI.
     */
    public function topSellingProducts(Request $request)
    {
        try {
            $limit = min(max((int) $request->input('limit', 10), 1), 50);
            $includeUnsold = $request->boolean('include_unsold', true);

            $items = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.payment_status', 'paid')
                ->select(
                    'order_items.product_id',
                    DB::raw('SUM(order_items.quantity) as sales'),
                    DB::raw('SUM(order_items.subtotal) as revenue')
                )
                ->groupBy('order_items.product_id')
                ->orderByRaw('SUM(order_items.quantity) DESC')
                ->limit($limit)
                ->get();

            $productIds = $items->pluck('product_id')->filter()->unique()->values();
            $products = $productIds->isNotEmpty()
                ? Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id')
                : collect();

            $data = $items->map(function ($row) use ($products) {
                return $this->formatTopSellingItem($row, $products->get($row->product_id));
            })->values();

            // When no paid orders have items, optionally return top products with 0 sales so dashboard can show something
            if ($data->isEmpty() && $includeUnsold) {
                $fallbackProducts = Product::with('category')
                    ->orderBy('name')
                    ->limit($limit)
                    ->get();
                foreach ($fallbackProducts as $product) {
                    $data->push($this->formatTopSellingItem(
                        (object) ['product_id' => $product->id, 'sales' => 0, 'revenue' => 0],
                        $product
                    ));
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Top selling products error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load top selling products.',
                'data' => [],
                'total' => 0,
            ], 500);
        }
    }

    /**
     * Build a single top-selling item for API response (with full product details).
     */
    private function formatTopSellingItem($row, $product): array
    {
        $revenue = (float) ($row->revenue ?? 0);
        $sales = (int) ($row->sales ?? 0);
        $name = $product ? $product->name : 'Unknown';
        $imageUrl = $product ? $product->image_url : null;
        $categoryName = $product && $product->relationLoaded('category') && $product->category
            ? $product->category->name
            : null;
        $price = $product ? (float) $product->price : null;

        return [
            'product_id' => $row->product_id,
            'product_name' => $name,
            'product_image_url' => $imageUrl,
            'category_name' => $categoryName,
            'price' => $price,
            'sales' => $sales,
            'sales_display' => $sales . ' sales',
            'revenue' => round($revenue, 2),
            'revenue_formatted' => number_format($revenue, 0) . ' AED',
            'revenue_display' => $this->formatRevenueShort($revenue),
        ];
    }

    /**
     * Format revenue for UI: 22000 -> "22K", 1500 -> "1.5K", 500 -> "500"
     */
    private function formatRevenueShort(float $amount): string
    {
        $amount = (float) $amount;
        if ($amount >= 1000000) {
            return round($amount / 1000000, 1) . 'M';
        }
        if ($amount >= 1000) {
            return round($amount / 1000, 1) . 'K';
        }
        return (string) (int) round($amount);
    }
}
