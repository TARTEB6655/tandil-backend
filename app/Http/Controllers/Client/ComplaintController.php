<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\Visit;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $complaints = Complaint::where('client_id', $user->id)
            ->with(['visit.subscription', 'visit.technician', 'visit.supervisor'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('client.complaints.index', compact('complaints'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $visitId = $request->get('visit_id');
        
        // Get all visits that belong to the client's subscriptions
        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        $visits = Visit::whereIn('subscription_id', $subscriptionIds)
            ->with(['subscription', 'technician', 'supervisor', 'area'])
            ->orderBy('scheduled_date', 'desc')
            ->get();

        $selectedVisit = null;
        if ($visitId) {
            $selectedVisit = $visits->firstWhere('id', $visitId);
        }

        return view('client.complaints.create', compact('visits', 'selectedVisit'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'notes' => 'required|string|max:1000',
        ]);

        // Verify the visit belongs to the client
        $visit = Visit::with('subscription')->findOrFail($request->input('visit_id'));
        
        if (!$visit->subscription || $visit->subscription->client_id !== $user->id) {
            return back()->withErrors(['visit_id' => 'You can only file complaints for your own visits.'])->withInput();
        }

        // Check if complaint already exists for this visit
        $existingComplaint = Complaint::where('visit_id', $visit->id)
            ->where('client_id', $user->id)
            ->first();

        if ($existingComplaint) {
            return back()->withErrors(['visit_id' => 'You have already filed a complaint for this visit.'])->withInput();
        }

        $complaint = Complaint::create([
            'visit_id' => $visit->id,
            'client_id' => $user->id,
            'notes' => $request->input('notes'),
            'status' => 'open',
        ]);

        return redirect()->route('client.complaints.show', $complaint->id)
            ->with('success', 'Complaint filed successfully.');
    }

    public function show($id)
    {
        $user = Auth::user();
        $complaint = Complaint::where('client_id', $user->id)
            ->with(['visit.subscription', 'visit.technician', 'visit.supervisor', 'visit.area', 'visit.photos'])
            ->findOrFail($id);

        return view('client.complaints.show', compact('complaint'));
    }
}
