<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:area_manager']);
    }

    public function index(Request $request): View
    {
        $search = $request->get('search', '');
        $areaId = $request->get('area');
        
        $reportsQuery = Report::with(['visit.subscription.client', 'supervisor']);
        
        if ($areaId) {
            $visitIds = Visit::where('area_id', $areaId)->pluck('id');
            $reportsQuery->whereIn('visit_id', $visitIds);
        }
        
        if ($search) {
            $reportsQuery->where(function($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhere('technician_notes', 'LIKE', "%{$search}%")
                  ->orWhere('supervisor_notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('visit.subscription.client', function($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        $reports = $reportsQuery->orderBy('created_at', 'desc')->paginate(15);

        return view('areamanager.reports.index', compact('reports', 'search', 'areaId'));
    }

    public function show($id): View
    {
        $report = Report::with(['visit.subscription.client', 'visit.technician', 'visit.supervisor', 'visit.area', 'visit.photos', 'supervisor'])
            ->findOrFail($id);

        return view('areamanager.reports.show', compact('report'));
    }
}

