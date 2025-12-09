<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\Report;
use App\Models\Order;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * Show the client dashboard.
     */
    public function index(\Illuminate\Http\Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');

        // Statistics
        $totalSubscriptions = Subscription::where('client_id', $user->id)->count();
        $activeSubscriptions = Subscription::where('client_id', $user->id)
            ->where('payment_status', 'paid')
            ->where('end_date', '>=', Carbon::today())
            ->count();
        
        // Get all visits for this client's subscriptions
        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        $totalVisits = Visit::whereIn('subscription_id', $subscriptionIds)->count();
        $completedVisits = Visit::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'completed')
            ->count();
        $pendingVisits = Visit::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'pending')
            ->count();
        $inProgressVisits = Visit::whereIn('subscription_id', $subscriptionIds)
            ->whereIn('status', ['accepted', 'started'])
            ->count();
        
        // Reports - get reports for visits belonging to this client's subscriptions
        $visitIds = Visit::whereIn('subscription_id', $subscriptionIds)->pluck('id');
        $totalReports = Report::whereIn('visit_id', $visitIds)->count();
        $approvedReports = Report::whereIn('visit_id', $visitIds)
            ->where('status', 'approved')
            ->count();
        
        // Orders
        $totalOrders = Order::where('user_id', $user->id)->count();
        $pendingOrders = Order::where('user_id', $user->id)
            ->where('order_status', 'pending')
            ->count();
        $completedOrders = Order::where('user_id', $user->id)
            ->where('order_status', 'delivered')
            ->count();
        $totalSpent = Order::where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->sum('total_amount');
        
        // Complaints
        $totalComplaints = Complaint::where('client_id', $user->id)->count();
        $pendingComplaints = Complaint::where('client_id', $user->id)
            ->where('status', 'pending')
            ->count();
        
        // Recent data (filtered by search if provided)
        $recentSubscriptionsQuery = Subscription::where('client_id', $user->id);
        if ($search) {
            $recentSubscriptionsQuery->where(function($q) use ($search) {
                $q->where('plan', 'LIKE', "%{$search}%")
                  ->orWhere('payment_reference', 'LIKE', "%{$search}%");
            });
        }
        $recentSubscriptions = $recentSubscriptionsQuery->with('visits')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        $recentVisitsQuery = Visit::whereIn('subscription_id', $subscriptionIds);
        if ($search) {
            $recentVisitsQuery->where(function($q) use ($search) {
                $q->where('status', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('technician', function($tq) use ($search) {
                      $tq->where('name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('supervisor', function($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        $recentVisits = $recentVisitsQuery->with(['technician', 'supervisor', 'subscription'])
            ->orderBy('scheduled_date', 'desc')
            ->take(5)
            ->get();
        
        $recentReportsQuery = Report::whereIn('visit_id', $visitIds);
        if ($search) {
            $recentReportsQuery->where(function($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhere('technician_notes', 'LIKE', "%{$search}%")
                  ->orWhere('supervisor_notes', 'LIKE', "%{$search}%");
            });
        }
        $recentReports = $recentReportsQuery->with(['visit.subscription', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        $recentOrdersQuery = Order::where('user_id', $user->id);
        if ($search) {
            $recentOrdersQuery->where(function($q) use ($search) {
                $q->where('payment_reference', 'LIKE', "%{$search}%")
                  ->orWhere('order_status', 'LIKE', "%{$search}%");
            });
        }
        $recentOrders = $recentOrdersQuery->with('items.product')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        $recentComplaintsQuery = Complaint::where('client_id', $user->id);
        if ($search) {
            $recentComplaintsQuery->where('notes', 'LIKE', "%{$search}%");
        }
        $recentComplaints = $recentComplaintsQuery->with(['visit.subscription'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        // Subscription spending
        $subscriptionSpending = Subscription::where('client_id', $user->id)
            ->where('payment_status', 'paid')
            ->sum('amount');
        
        // Visits by status
        $visitsByStatus = Visit::whereIn('subscription_id', $subscriptionIds)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        
        // Orders by status
        $ordersByStatus = Order::where('user_id', $user->id)
            ->select('order_status', DB::raw('COUNT(*) as count'))
            ->groupBy('order_status')
            ->get();
        
        // Monthly spending (last 6 months)
        $monthlySpending = Order::where('user_id', $user->id)
            ->where('payment_status', 'paid')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total_amount) as amount')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        return view('client.dashboard', compact(
            'totalSubscriptions',
            'activeSubscriptions',
            'totalVisits',
            'completedVisits',
            'pendingVisits',
            'inProgressVisits',
            'totalReports',
            'approvedReports',
            'totalOrders',
            'pendingOrders',
            'completedOrders',
            'totalSpent',
            'subscriptionSpending',
            'totalComplaints',
            'pendingComplaints',
            'recentSubscriptions',
            'recentVisits',
            'recentReports',
            'recentOrders',
            'recentComplaints',
            'visitsByStatus',
            'ordersByStatus',
            'monthlySpending',
            'search'
        ));
    }
}
