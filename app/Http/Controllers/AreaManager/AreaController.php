<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:area_manager']);
    }

    public function index(Request $request): View
    {
        $search = $request->get('search', '');
        
        $areasQuery = Area::with(['supervisors', 'technicians', 'visits']);
        
        if ($search) {
            $areasQuery->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }
        
        $areas = $areasQuery->orderBy('name', 'asc')->paginate(15);

        return view('areamanager.areas.index', compact('areas', 'search'));
    }

    public function show($id): View
    {
        $area = Area::with(['supervisors', 'technicians', 'visits.subscription.client', 'visits.technician'])
            ->findOrFail($id);

        // Statistics for this area
        $totalVisits = $area->visits()->count();
        $pendingVisits = $area->visits()->where('status', 'pending')->count();
        $completedVisits = $area->visits()->where('status', 'completed')->count();
        $totalSupervisors = $area->supervisors()->count();
        $totalTechnicians = $area->technicians()->count();

        return view('areamanager.areas.show', compact('area', 'totalVisits', 'pendingVisits', 'completedVisits', 'totalSupervisors', 'totalTechnicians'));
    }
}

