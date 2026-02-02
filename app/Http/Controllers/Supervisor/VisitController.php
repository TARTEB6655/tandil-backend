<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:supervisor']);
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');
        
        // Get IDs of supervised areas
        $areaIds = $user->supervisedAreaIds();
        
        if (empty($areaIds)) {
            $visits = collect();
        } else {
            $visitsQuery = Visit::whereIn('area_id', $areaIds);
            
            if ($search) {
                $visitsQuery->where(function($q) use ($search) {
                    $q->where('status', 'LIKE', "%{$search}%")
                      ->orWhere('notes', 'LIKE', "%{$search}%")
                      ->orWhereHas('subscription.client', function($cq) use ($search) {
                          $cq->where('name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('technician', function($tq) use ($search) {
                          $tq->where('name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('area', function($aq) use ($search) {
                          $aq->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }
            
            $visits = $visitsQuery->with(['subscription.client', 'technician', 'area', 'photos', 'report'])
                ->orderBy('scheduled_date', 'desc')
                ->paginate(15);
        }

        return view('supervisor.visits.index', compact('visits', 'search'));
    }

    public function show($id): View
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        
        $visit = Visit::whereIn('area_id', $areaIds)
            ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos', 'report', 'complaints'])
            ->findOrFail($id);

        return view('supervisor.visits.show', compact('visit'));
    }

    public function approve(Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        
        $visit = Visit::whereIn('area_id', $areaIds)->findOrFail($id);
        
        $visit->status = 'approved';
        $visit->approved_by = $user->id;
        $visit->approved_at = now();
        $visit->save();
        
        return back()->with('success', 'Visit approved successfully.');
    }

    public function reject(Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        
        $visit = Visit::whereIn('area_id', $areaIds)->findOrFail($id);
        
        $visit->status = 'rejected';
        $visit->approved_by = $user->id;
        $visit->approved_at = now();
        $visit->save();
        
        return back()->with('success', 'Visit rejected.');
    }
}

