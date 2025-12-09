<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\Visit;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:technician']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $search = $request->get('search', '');
        
        $complaintsQuery = Complaint::whereHas('visit', function($q) use ($user) {
            $q->where('technician_id', $user->id);
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

        return view('technician.complaints.index', compact('complaints', 'search'));
    }

    public function show($id)
    {
        $user = Auth::user();
        
        $complaint = Complaint::whereHas('visit', function($q) use ($user) {
            $q->where('technician_id', $user->id);
        })
        ->with(['visit.subscription.client', 'visit.technician', 'visit.supervisor', 'visit.area', 'visit.photos'])
        ->findOrFail($id);

        return view('technician.complaints.show', compact('complaint'));
    }
}
