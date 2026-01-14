<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = Complaint::with(['client', 'visit']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->whereHas('client', function($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('email', 'LIKE', "%{$request->search}%");
            })->orWhere('notes', 'LIKE', "%{$request->search}%");
        }

        $complaints = $query->orderBy('created_at', 'desc')->paginate(15);

        // Statistics
        $stats = [
            'total' => Complaint::count(),
            'pending' => Complaint::where('status', 'pending')->count(),
            'in_progress' => Complaint::where('status', 'in_progress')->count(),
            'resolved' => Complaint::where('status', 'resolved')->count(),
        ];

        return view('admin.complaints.index', compact('complaints', 'stats'));
    }

    public function show($id)
    {
        $complaint = Complaint::with(['client', 'visit.technician', 'visit.supervisor'])->findOrFail($id);
        $supervisors = User::where('role', 'supervisor')->get();
        
        return view('admin.complaints.show', compact('complaint', 'supervisors'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,resolved',
            'notes' => 'nullable|string',
        ]);

        $complaint = Complaint::findOrFail($id);
        $complaint->update([
            'status' => $request->status,
            'notes' => $request->notes ?? $complaint->notes,
        ]);

        return redirect()->back()->with('success', 'Complaint status updated successfully');
    }

    public function assignSupervisor(Request $request, $id)
    {
        $request->validate([
            'supervisor_id' => 'required|exists:users,id',
        ]);

        $complaint = Complaint::findOrFail($id);
        $visit = $complaint->visit;
        
        if ($visit) {
            $visit->update(['supervisor_id' => $request->supervisor_id]);
        }

        return redirect()->back()->with('success', 'Supervisor assigned successfully');
    }
}





