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

    public function index(Request $request)
    {
        $query = Area::with(['supervisors', 'technicians', 'visits']);

        if ($request->has('search') && $request->search) {
            $query->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('description', 'LIKE', "%{$request->search}%");
        }

        $areas = $query->orderBy('name', 'asc')->paginate(15);
        
        return view('admin.areas.index', compact('areas'));
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
            'supervisors' => 'nullable|array',
            'supervisors.*' => 'exists:users,id',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
        ]);

        $area = Area::create([
            'name' => $request->name,
            'description' => $request->description,
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
            'supervisors' => 'nullable|array',
            'supervisors.*' => 'exists:users,id',
            'technicians' => 'nullable|array',
            'technicians.*' => 'exists:users,id',
        ]);

        $area = Area::findOrFail($id);
        $area->update([
            'name' => $request->name,
            'description' => $request->description,
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

