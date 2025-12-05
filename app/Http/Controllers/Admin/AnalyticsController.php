<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Visit;
use App\Models\Subscription;
use App\Models\Product;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Get revenue data for charts (last 6 months)
     */
    public function revenue(Request $request)
    {
        $months = $request->get('months', 6);
        
        $data = Order::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', Carbon::now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        return response()->json([
            'labels' => $data->pluck('month'),
            'revenue' => $data->pluck('revenue'),
            'orders' => $data->pluck('orders'),
        ]);
    }

    /**
     * Get visits data for charts (last 8 weeks)
     */
    public function visits(Request $request)
    {
        $weeks = $request->get('weeks', 8);
        
        $data = Visit::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%u") as week'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subWeeks($weeks))
            ->groupBy('week')
            ->orderBy('week', 'asc')
            ->get();

        return response()->json([
            'labels' => $data->pluck('week'),
            'counts' => $data->pluck('count'),
        ]);
    }

    /**
     * Get subscription distribution data
     */
    public function subscriptions()
    {
        try {
            // Get subscriptions grouped by plan
            $data = Subscription::select('plan', DB::raw('COUNT(*) as count'))
                ->where('payment_status', 'paid')
                ->where('end_date', '>=', Carbon::today())
                ->groupBy('plan')
                ->get();

            $labels = $data->pluck('plan')->map(function($plan) {
                // Format plan names nicely
                return str_replace('_', ' ', ucfirst($plan));
            })->toArray();
            $counts = $data->pluck('count')->toArray();

            // If no data, return empty arrays with a default label
            if (empty($labels)) {
                return response()->json([
                    'labels' => ['No Active Subscriptions'],
                    'counts' => [0],
                ]);
            }

            return response()->json([
                'labels' => $labels,
                'counts' => $counts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'labels' => ['Error'],
                'counts' => [0],
            ]);
        }
    }

    /**
     * Get visit status distribution
     */
    public function visitStatus()
    {
        $data = Visit::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'labels' => $data->pluck('status'),
            'counts' => $data->pluck('count'),
        ]);
    }

    /**
     * Get technician performance data
     */
    public function technicianPerformance()
    {
        $data = User::where('role', 'technician')
            ->whereHas('visits')
            ->withCount(['visits' => function($query) {
                $query->where('status', 'completed');
            }])
            ->orderBy('visits_count', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'technicians' => $data->pluck('name'),
            'visits' => $data->pluck('visits_count'),
        ]);
    }

    /**
     * Get area performance data
     */
    public function areaPerformance()
    {
        $data = Area::withCount(['visits' => function($query) {
                $query->whereNotNull('area_id');
            }])
            ->orderBy('visits_count', 'desc')
            ->get();

        return response()->json([
            'areas' => $data->pluck('name'),
            'visits' => $data->pluck('visits_count'),
        ]);
    }

    /**
     * Get order status distribution
     */
    public function orderStatus()
    {
        $data = Order::select('order_status', DB::raw('COUNT(*) as count'))
            ->groupBy('order_status')
            ->get();

        return response()->json([
            'labels' => $data->pluck('order_status'),
            'counts' => $data->pluck('count'),
        ]);
    }

    /**
     * Get payment status distribution
     */
    public function paymentStatus()
    {
        $data = Order::select('payment_status', DB::raw('COUNT(*) as count'))
            ->groupBy('payment_status')
            ->get();

        return response()->json([
            'labels' => $data->pluck('payment_status'),
            'counts' => $data->pluck('count'),
        ]);
    }
}
