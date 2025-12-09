<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Visit;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:technician']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $search = $request->get('search', '');
        $visitIds = Visit::where('technician_id', $user->id)->pluck('id');
        
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

        return view('technician.reports.index', compact('reports', 'search'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $visitId = $request->get('visit_id');
        
        // Get completed visits that don't have a report yet
        $visits = Visit::where('technician_id', $user->id)
            ->where('status', 'completed')
            ->whereDoesntHave('report')
            ->with(['subscription.client', 'area'])
            ->orderBy('scheduled_date', 'desc')
            ->get();
        
        $selectedVisit = null;
        if ($visitId) {
            $selectedVisit = $visits->firstWhere('id', $visitId);
        }
        
        $products = Product::where('status', 'active')->orderBy('name')->get();

        return view('technician.reports.create', compact('visits', 'selectedVisit', 'products'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'technician_notes' => 'required|string|max:5000',
            'recommended_products' => 'nullable|array',
            'recommended_products.*' => 'exists:products,id',
        ]);
        
        // Verify the visit belongs to this technician and is completed
        $visit = Visit::where('id', $validated['visit_id'])
            ->where('technician_id', $user->id)
            ->where('status', 'completed')
            ->firstOrFail();
        
        // Check if report already exists
        if ($visit->report) {
            return back()->withErrors(['visit_id' => 'A report already exists for this visit.'])->withInput();
        }
        
        $report = Report::create([
            'visit_id' => $visit->id,
            'technician_notes' => $validated['technician_notes'],
            'recommended_products' => $validated['recommended_products'] ?? [],
            'status' => 'pending',
        ]);
        
        return redirect()->route('technician.reports.index')->with('success', 'Report created successfully and is pending supervisor review.');
    }

    public function show($id)
    {
        $user = Auth::user();
        $visitIds = Visit::where('technician_id', $user->id)->pluck('id');
        
        $report = Report::whereIn('visit_id', $visitIds)
            ->with(['visit.subscription.client', 'visit.technician', 'visit.supervisor', 'visit.area', 'visit.photos', 'supervisor'])
            ->findOrFail($id);

        return view('technician.reports.show', compact('report'));
    }
}

