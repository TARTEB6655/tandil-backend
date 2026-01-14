<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Complaint;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ComplaintController extends Controller
{
    public function __construct()
    {
        // Protect all routes with auth middleware
        $this->middleware('auth:sanctum');
    }

    /**
     * List all complaints (admin) or user's complaints.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin') || $user->hasRole('area_manager') || $user->hasRole('supervisor') || $user->hasRole('hr')) {
            // Admin and managers can see all complaints
            $complaints = Complaint::with(['visit', 'client'])->get();
        } else {
            // Other users see only their own complaints
            $complaints = Complaint::with('visit')
                ->where('client_id', $user->id)
                ->get();
        }

        return ApiResponse::success('Complaints retrieved successfully.', $complaints);
    }

    /**
     * Create a new complaint by a client.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'notes' => 'required|string|max:1000',
        ]);

        // Check that the visit belongs to the user or user is authorized
        $visit = Visit::with('subscription')->findOrFail($request->input('visit_id'));

        if ($visit->subscription && $visit->subscription->client_id !== $user->id && !$user->hasRole('admin')) {
            return ApiResponse::error('You can only file complaints for your own visits', 403);
        }

        $complaint = Complaint::create([
            'visit_id' => $visit->id,
            'client_id' => $user->id,
            'notes' => $request->input('notes'),
            'status' => 'open',
        ]);

        return ApiResponse::success('Complaint created successfully.', $complaint, 201);
    }

    /**
     * View a specific complaint.
     */
    public function show(Request $request, $id)
    {
        $complaint = Complaint::with(['visit', 'client'])->findOrFail($id);
        $user = $request->user();

        if ($user->hasRole('admin') || $user->hasRole('area_manager') || $user->hasRole('supervisor') || $user->hasRole('hr') || $complaint->client_id === $user->id) {
            return ApiResponse::success('Complaint retrieved successfully.', $complaint);
        }

        return ApiResponse::error('Forbidden', 403);
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

        return ApiResponse::success('Complaint updated successfully.', $complaint);
    }

    /**
     * Delete a complaint (admin only).
     */
    public function destroy(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return ApiResponse::error('Forbidden', 403);
        }

        $complaint->delete();

        return ApiResponse::success('Complaint deleted successfully.');
    }
}
