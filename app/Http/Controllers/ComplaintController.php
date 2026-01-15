<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Complaint;
use App\Models\Visit;
use App\Models\User;
use App\Notifications\AdminNotification;
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

        // 🔔 Send notifications to relevant users
        try {
            // Notify all admins
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'New Complaint Received',
                    "A new complaint has been filed by {$user->name} for visit #{$visit->id}. Notes: " . substr($request->input('notes'), 0, 100)
                ));
            }

            // Notify supervisors if visit has area
            if ($visit->area_id) {
                $area = \App\Models\Area::with('supervisors')->find($visit->area_id);
                if ($area && $area->supervisors) {
                    foreach ($area->supervisors as $supervisor) {
                        $supervisor->notify(new AdminNotification(
                            'New Complaint in Your Area',
                            "A new complaint has been filed for visit #{$visit->id} in your area."
                        ));
                    }
                }
            }

            // Notify area managers
            $areaManagers = User::role('area_manager')->get();
            foreach ($areaManagers as $areaManager) {
                $areaManager->notify(new AdminNotification(
                    'New Complaint Filed',
                    "A new complaint has been filed. Please review complaint #{$complaint->id}."
                ));
            }
        } catch (\Exception $e) {
            // Log error but don't break the flow
            \Log::error('Failed to send complaint notifications: ' . $e->getMessage());
        }

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
            return ApiResponse::error('Complaint not found', 404);
        }

        $user = $request->user();

        // Only admin, area_manager, or supervisor can update complaint status
        if (! $user->hasAnyRole(['admin', 'area_manager', 'supervisor'])) {
            return ApiResponse::error('Forbidden', 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'in:open,in_progress,resolved,escalated',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldStatus = $complaint->status;
        
        if ($request->has('status')) {
            $complaint->status = $request->input('status');
        }
        if ($request->has('notes')) {
            $complaint->notes = $request->input('notes');
        }

        $complaint->save();

        // 🔔 Send notifications based on status change
        try {
            if ($request->has('status') && $oldStatus !== $complaint->status) {
                // Notify client when complaint status changes
                $client = User::find($complaint->client_id);
                if ($client) {
                    $client->notify(new AdminNotification(
                        'Complaint Status Updated',
                        "Your complaint #{$complaint->id} status has been changed to: " . ucfirst(str_replace('_', ' ', $complaint->status))
                    ));
                }

                // If escalated, notify area manager
                if ($complaint->status === 'escalated') {
                    $areaManagers = User::role('area_manager')->get();
                    foreach ($areaManagers as $areaManager) {
                        $areaManager->notify(new AdminNotification(
                            'Complaint Escalated',
                            "Complaint #{$complaint->id} has been escalated and requires your attention."
                        ));
                    }
                }

                // If resolved, notify admins
                if ($complaint->status === 'resolved') {
                    $admins = User::role('admin')->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new AdminNotification(
                            'Complaint Resolved',
                            "Complaint #{$complaint->id} has been resolved."
                        ));
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send complaint update notifications: ' . $e->getMessage());
        }

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
