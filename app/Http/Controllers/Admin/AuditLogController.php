<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of audit logs.
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Check if activity_log table exists
        if (!Schema::hasTable('activity_log')) {
            $logs = collect([])->paginate(50);
            $stats = [
                'total' => 0,
                'today' => 0,
                'this_week' => 0,
            ];
            return view('admin.audit-logs.index', compact('logs', 'stats'))
                ->with('warning', 'Activity log table does not exist. Please run migrations.');
        }
        
        try {
            $query = DB::table('activity_log');

            // Filter by type
            if ($request->has('type') && $request->type) {
                $query->where('log_name', $request->type);
            }

            // Filter by user
            if ($request->has('user_id') && $request->user_id) {
                $query->where('causer_id', $request->user_id);
            }

            // Date range
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $logs = $query->orderBy('created_at', 'desc')->paginate(50);

            // Statistics
            $stats = [
                'total' => DB::table('activity_log')->count(),
                'today' => DB::table('activity_log')->whereDate('created_at', today())->count(),
                'this_week' => DB::table('activity_log')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching activity logs: ' . $e->getMessage());
            $logs = collect([])->paginate(50);
            $stats = [
                'total' => 0,
                'today' => 0,
                'this_week' => 0,
            ];
        }

        return view('admin.audit-logs.index', compact('logs', 'stats'));
    }

    /**
     * Display the specified audit log.
     * 
     * @param int $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show($id)
    {
        // Check if activity_log table exists
        if (!Schema::hasTable('activity_log')) {
            return redirect()->route('admin.audit-logs.index')
                ->with('error', 'Activity log table does not exist. Please run migrations.');
        }
        
        try {
            $log = DB::table('activity_log')->where('id', $id)->first();
            
            if (!$log) {
                return redirect()->route('admin.audit-logs.index')
                    ->with('error', 'Log not found');
            }

            return view('admin.audit-logs.show', compact('log'));
        } catch (\Exception $e) {
            Log::error('Error fetching activity log: ' . $e->getMessage());
            return redirect()->route('admin.audit-logs.index')
                ->with('error', 'Error loading log details.');
        }
    }
}


