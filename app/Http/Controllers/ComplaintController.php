<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ComplaintController extends Controller
{
    public function __construct()
    {
        // Protect all routes with auth middleware; adjust roles/permissions as needed
        $this->middleware('auth:sanctum');
    }

    /**
     * List all complaints (admin) or user's complaints.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin') || $user->hasRole('area_manager') || $user->hasRole('supervisor')) {
            // Admin and managers can see all complaints
            $complaints = Complaint::with(['visit', 'client'])->get();
        } else {
            // Other users see only their own complaints
            $complaints = Complaint::with('visit')
                ->where('client_id', $user->id)
                ->get();
        }

        return response()->json(['status' => true, 'data' => $complaints]);
    }

    /**
     * Create a new complaint by a client.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'visit_id' => 'required|exists:visits,id',
            'notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Check that the visit belongs to the user or user is authorized
        $visit = Visit::find($request->input('visit_id'));
        if ($visit->subscription->client_id !== $user->id) {
            return response()->json(['status' => false, 'message' => 'You can only file complaints for your own visits'], 403);
        }

        $complaint = Complaint::create([
            'visit_id' => $visit->id,
            'client_id' => $user->id,
            'notes' => $request->input('notes'),
            'status' => 'open',
        ]);

        return response()->json(['status' => true, 'data' => $complaint], 201);
    }

    /**
     * View a specific complaint.
     */
    public function show(Request $request, $id)
    {
        $complaint = Complaint::with(['visit', 'client'])->find($id);
        if (! $complaint) {
            return response()->json(['status' => false, 'message' => 'Complaint not found'], 404);
        }

        $user = $request->user();

        if ($user->hasRole('admin') || $user->hasRole('area_manager') || $user->hasRole('supervisor') || $complaint->client_id === $user->id) {
            return response()->json(['status' => true, 'data' => $complaint]);
        }

        return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
    }

    /**
     * Update complaint status or notes (escalate, resolve, etc.).
     */
    public function update(Request $request, $id)
    {
        $complaint = Complaint::find($id);

        if (! $complaint) {
            return response()->json(['status' => false, 'message' => 'Complaint not found'], 404);
        }

        $user = $request->user();

        // Only admin, area_manager, or supervisor can update complaint status
        if (! $user->hasAnyRole(['admin', 'area_manager', 'supervisor'])) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'in:open,in_progress,resolved,escalated',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('status')) {
            $complaint->status = $request->input('status');
        }
        if ($request->has('notes')) {
            $complaint->notes = $request->input('notes');
        }

        $complaint->save();

        return response()->json(['status' => true, 'data' => $complaint]);
    }

    /**
     * Delete a complaint (admin only).
     */
    public function destroy(Request $request, $id)
    {
        $complaint = Complaint::find($id);

        if (! $complaint) {
            return response()->json(['status' => false, 'message' => 'Complaint not found'], 404);
        }

        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $complaint->delete();

        return response()->json(['status' => true, 'message' => 'Complaint deleted']);
    }
}
