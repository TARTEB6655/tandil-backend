<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ComplaintController extends Controller
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
            $complaints = collect();
        } else {
            $complaintsQuery = Complaint::whereHas('visit', function($q) use ($areaIds) {
                $q->whereIn('area_id', $areaIds);
            });
            
            if ($search) {
                $complaintsQuery->where(function($q) use ($search) {
                    $q->where('notes', 'LIKE', "%{$search}%")
                      ->orWhere('status', 'LIKE', "%{$search}%")
                      ->orWhereHas('visit.subscription.client', function($cq) use ($search) {
                          $cq->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }
            
            $complaints = $complaintsQuery->with(['visit.subscription.client', 'visit.technician', 'visit.supervisor'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }

        return view('supervisor.complaints.index', compact('complaints', 'search'));
    }

    public function show($id): View
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        
        $complaint = Complaint::whereHas('visit', function($q) use ($areaIds) {
            $q->whereIn('area_id', $areaIds);
        })
        ->with(['visit.subscription.client', 'visit.technician', 'visit.supervisor', 'visit.area', 'visit.photos'])
        ->findOrFail($id);

        return view('supervisor.complaints.show', compact('complaint'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        
        $complaint = Complaint::whereHas('visit', function($q) use ($areaIds) {
            $q->whereIn('area_id', $areaIds);
        })->findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,resolved,escalated',
            'notes' => 'nullable|string|max:2000',
        ]);
        
        $complaint->status = $validated['status'];
        if ($request->has('notes')) {
            $complaint->notes = $validated['notes'];
        }
        $complaint->save();
        
        return back()->with('success', 'Complaint updated successfully.');
    }
}

