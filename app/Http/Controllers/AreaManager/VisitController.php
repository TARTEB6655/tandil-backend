<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:area_manager']);
    }

    public function index(Request $request): View
    {
        $search = $request->get('search', '');
        $areaId = $request->get('area');
        
        $visitsQuery = Visit::with(['subscription.client', 'technician', 'area', 'photos', 'report']);
        
        if ($areaId) {
            $visitsQuery->where('area_id', $areaId);
        }
        
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
        
        $visits = $visitsQuery->orderBy('scheduled_date', 'desc')->paginate(15);

        return view('areamanager.visits.index', compact('visits', 'search', 'areaId'));
    }

    public function show($id): View
    {
        $visit = Visit::with(['subscription.client', 'technician', 'supervisor', 'area', 'photos', 'report', 'complaints'])
            ->findOrFail($id);

        return view('areamanager.visits.show', compact('visit'));
    }
}

