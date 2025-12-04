<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = Visit::with(['subscription.client', 'technician', 'area', 'report']);

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('subscription.client', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by area
        if ($request->has('area_id') && $request->area_id) {
            $query->where('area_id', $request->area_id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('scheduled_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('scheduled_date', '<=', $request->date_to);
        }

        $visits = $query->orderBy('scheduled_date', 'desc')->paginate(15);
        $areas = Area::all();

        return view('admin.visits.index', compact('visits', 'areas'));
    }

    public function show($id)
    {
        $visit = Visit::with([
            'subscription.client',
            'technician',
            'supervisor',
            'area',
            'photos',
            'report',
            'complaints'
        ])->findOrFail($id);

        return view('admin.visits.show', compact('visit'));
    }

    public function assignTechnician(Request $request, $id)
    {
        $request->validate([
            'technician_id' => 'required|exists:users,id',
        ]);

        $visit = Visit::findOrFail($id);
        $technician = User::findOrFail($request->technician_id);

        if ($technician->role !== 'technician') {
            return redirect()->back()->with('error', 'Selected user is not a technician');
        }

        $visit->technician_id = $request->technician_id;
        $visit->save();

        return redirect()->back()->with('success', 'Technician assigned successfully');
    }

    public function assignSupervisor(Request $request, $id)
    {
        $request->validate([
            'supervisor_id' => 'required|exists:users,id',
        ]);

        $visit = Visit::findOrFail($id);
        $supervisor = User::findOrFail($request->supervisor_id);

        if ($supervisor->role !== 'supervisor') {
            return redirect()->back()->with('error', 'Selected user is not a supervisor');
        }

        $visit->supervisor_id = $request->supervisor_id;
        $visit->save();

        return redirect()->back()->with('success', 'Supervisor assigned successfully');
    }
}
