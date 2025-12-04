<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = Report::with(['visit.subscription.client', 'visit.technician', 'visit.supervisor']);

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('visit.subscription.client', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.reports.index', compact('reports'));
    }

    public function show($id)
    {
        $report = Report::with([
            'visit.subscription.client',
            'visit.technician',
            'visit.supervisor',
            'visit.photos',
            'visit.area'
        ])->findOrFail($id);

        return view('admin.reports.show', compact('report'));
    }

    public function approve($id)
    {
        $report = Report::findOrFail($id);
        $report->status = 'approved';
        $report->save();

        return redirect()->back()->with('success', 'Report approved successfully');
    }

    public function sendToClient($id)
    {
        $report = Report::with('visit.subscription.client')->findOrFail($id);
        
        // Here you would send email/notification to client
        // For now, just mark as sent
        $report->status = 'sent_to_client';
        $report->save();

        return redirect()->back()->with('success', 'Report sent to client successfully');
    }
}
