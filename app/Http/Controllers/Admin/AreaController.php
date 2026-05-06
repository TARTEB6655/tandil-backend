<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Zone Assignment hub: see supervisors with zones, technicians with zone + specialization, and assign from zones.
     */
    public function zoneAssignment(Request $request)
    {
        $areas = Area::with(['supervisors', 'technicians'])->orderBy('name')->get();
        $supervisors = User::role('supervisor')->with(['employee', 'supervisedAreas'])->orderBy('name')->get();
        $technicians = User::role('technician')->with(['employee', 'assignedAreas'])->orderBy('name')->get();
        return view('admin.zone-assignment.index', compact('areas', 'supervisors', 'technicians'));
    }

    public function index(Request $request)
    {
        $query = Area::with(['supervisors', 'technicians', 'visits']);

        if ($request->has('search') && $request->search) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('location', 'LIKE', "%{$search}%")
                    ->orWhere('country', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $areas = $query->orderBy('priority', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return view('admin.areas.index', compact('areas'));
    }

    public function toggleActive(Request $request, int $id)
    {
        $area = Area::with('supervisors')->findOrFail($id);
        $area->is_active = ! (bool) $area->is_active;
        $area->save();

        $message = $area->is_active
            ? "Area '{$area->name}' is now enabled."
            : "Area '{$area->name}' is now disabled.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $area->id,
                    'is_active' => (bool) $area->is_active,
                    'name' => $area->name,
                    'latitude' => $area->latitude,
                    'longitude' => $area->longitude,
                    'location' => $area->location,
                    'country' => $area->country,
                    'supervisors' => $area->supervisors->pluck('name')->values(),
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function create()
    {
        $supervisors = User::where('role', 'supervisor')->get();
        $technicians = User::where('role', 'technician')->get();
        
        return view('admin.areas.create', compact('supervisors', 'technicians'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:10000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'service_radius_km' => 'nullable|numeric|min:0.1|max:1000',
            'supervisors' => 'nullable|array',
            'supervisors.*' => 'exists:users,id',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
        ]);

        $area = Area::create([
            'name' => $request->name,
            'description' => $request->description,
            'location' => $request->input('location'),
            'country' => $request->input('country', 'UAE'),
            'is_active' => $request->boolean('is_active', true),
            'priority' => (int) $request->input('priority', 100),
            'latitude' => $request->filled('latitude') ? (float) $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? (float) $request->input('longitude') : null,
            'service_radius_km' => $request->filled('service_radius_km') ? (float) $request->input('service_radius_km') : 30,
        ]);

        if ($request->has('supervisors')) {
            $area->supervisors()->sync($request->supervisors);
        }

        if ($request->has('technicians')) {
            $area->technicians()->sync($request->technicians);
        }

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area created successfully');
    }

    public function show($id)
    {
        $area = Area::with(['supervisors', 'technicians', 'visits', 'complaints'])->findOrFail($id);
        
        // Get statistics
        $totalVisits = $area->visits()->count();
        $completedVisits = $area->visits()->where('status', 'completed')->count();
        $pendingVisits = $area->visits()->where('status', 'pending')->count();
        $totalComplaints = $area->complaints()->count();
        
        return view('admin.areas.show', compact('area', 'totalVisits', 'completedVisits', 'pendingVisits', 'totalComplaints'));
    }

    public function edit($id)
    {
        $area = Area::with(['supervisors', 'technicians'])->findOrFail($id);
        $supervisors = User::where('role', 'supervisor')->get();
        $technicians = User::where('role', 'technician')->get();
        
        return view('admin.areas.edit', compact('area', 'supervisors', 'technicians'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:10000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'service_radius_km' => 'nullable|numeric|min:0.1|max:1000',
            'supervisors' => 'nullable|array',
            'supervisors.*' => 'exists:users,id',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
        ]);

        $area = Area::findOrFail($id);
        $area->update([
            'name' => $request->name,
            'description' => $request->description,
            'location' => $request->input('location'),
            'country' => $request->input('country', 'UAE'),
            'is_active' => $request->boolean('is_active', false),
            'priority' => (int) $request->input('priority', 100),
            'latitude' => $request->filled('latitude') ? (float) $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? (float) $request->input('longitude') : null,
            'service_radius_km' => $request->filled('service_radius_km') ? (float) $request->input('service_radius_km') : 30,
        ]);

        if ($request->has('supervisors')) {
            $area->supervisors()->sync($request->supervisors);
        } else {
            $area->supervisors()->detach();
        }

        if ($request->has('technicians')) {
            $area->technicians()->sync($request->technicians);
        } else {
            $area->technicians()->detach();
        }

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area updated successfully');
    }

    public function destroy($id)
    {
        $area = Area::findOrFail($id);
        $area->delete();

        return redirect()->route('admin.areas.index')
            ->with('success', 'Area deleted successfully');
    }
}





