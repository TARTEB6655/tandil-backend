<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        // Note: This is a basic implementation
        // In production, you'd want a dedicated audit_logs table
        
        $query = DB::table('activity_log'); // If using spatie/laravel-activitylog

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

        return view('admin.audit-logs.index', compact('logs', 'stats'));
    }

    public function show($id)
    {
        $log = DB::table('activity_log')->where('id', $id)->first();
        
        if (!$log) {
            return redirect()->route('admin.audit-logs.index')
                ->with('error', 'Log not found');
        }

        return view('admin.audit-logs.show', compact('log'));
    }
}

