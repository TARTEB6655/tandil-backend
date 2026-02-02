<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Visit;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:supervisor']);
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');
        $areaIds = $user->supervisedAreaIds();
        
        if (empty($areaIds)) {
            $reports = collect();
        } else {
            $visitIds = Visit::whereIn('area_id', $areaIds)->pluck('id');
            $reportsQuery = Report::whereIn('visit_id', $visitIds);
            
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
            
            $reports = $reportsQuery->with(['visit.subscription.client', 'supervisor'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }

        return view('supervisor.reports.index', compact('reports', 'search'));
    }

    public function show($id): View
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        $visitIds = Visit::whereIn('area_id', $areaIds)->pluck('id');
        
        $report = Report::whereIn('visit_id', $visitIds)
            ->with(['visit.subscription.client', 'visit.technician', 'visit.supervisor', 'visit.area', 'visit.photos', 'supervisor'])
            ->findOrFail($id);

        return view('supervisor.reports.show', compact('report'));
    }

    public function review($id): View
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        $visitIds = Visit::whereIn('area_id', $areaIds)->pluck('id');
        
        $report = Report::whereIn('visit_id', $visitIds)
            ->with(['visit.subscription.client', 'visit.technician', 'visit.area', 'visit.photos'])
            ->findOrFail($id);
        
        $products = Product::where('status', 'active')->orderBy('name')->get();

        return view('supervisor.reports.review', compact('report', 'products'));
    }

    public function finalize(Request $request, $id): RedirectResponse
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        $visitIds = Visit::whereIn('area_id', $areaIds)->pluck('id');
        
        $report = Report::whereIn('visit_id', $visitIds)->findOrFail($id);
        
        $validated = $request->validate([
            'supervisor_notes' => 'nullable|string|max:5000',
            'recommended_products' => 'nullable|array',
            'recommended_products.*' => 'exists:products,id',
            'status' => 'required|in:pending,approved',
        ]);
        
        $report->supervisor_id = $user->id;
        $report->supervisor_notes = $validated['supervisor_notes'] ?? null;
        $report->recommended_products = $validated['recommended_products'] ?? [];
        
        if ($validated['status'] === 'approved') {
            $report->status = 'approved';
            $report->approved_by = $user->id;
            $report->approved_at = now();
        } else {
            $report->status = 'pending';
        }
        
        $report->save();
        
        return redirect()->route('supervisor.reports.index')->with('success', 'Report finalized successfully.');
    }
}

