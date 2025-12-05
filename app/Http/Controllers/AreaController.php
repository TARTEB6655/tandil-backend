<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\User;
use App\Models\Visit;
use App\Models\Complaint;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AreaNotification;

class AreaController extends Controller
{
    public function __construct()
    {
        // Only allow authenticated users with area_manager role
        $this->middleware(['auth:sanctum', 'role:area_manager']);
    }

    /**
     * List all areas managed by the area manager.
     */
    public function index()
    {
        try {
            $areas = Area::with(['supervisors', 'technicians', 'visits'])->get();

            return response()->json([
                'status' => true,
                'data' => $areas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch areas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new area.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:areas,name',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false, 'errors'=>$validator->errors()], 422);
        }

        $area = Area::create($request->only(['name', 'description']));

        $this->logAction("Created new area: {$area->name}");

        return response()->json(['status' => true, 'data' => $area], 201);
    }

    /**
     * Show area details, including assigned users and visits.
     */
    public function show($id)
    {
        $area = Area::with(['supervisors', 'technicians', 'visits.client', 'visits.technician'])->find($id);

        if (!$area) {
            return response()->json(['status' => false, 'message' => 'Area not found'], 404);
        }

        // Performance metrics
        $totalVisits = $area->visits()->count();
        $completedVisits = $area->visits()->where('status', 'completed')->count();
        $teamSize = $area->supervisors()->count() + $area->technicians()->count();

        return response()->json([
            'status' => true,
            'data' => [
                'area' => $area,
                'metrics' => [
                    'total_visits' => $totalVisits,
                    'completed_visits' => $completedVisits,
                    'team_size' => $teamSize,
                ],
            ]
        ]);
    }

    /**
     * Update area details.
     */
    public function update(Request $request, $id)
    {
        $area = Area::find($id);

        if (!$area) {
            return response()->json(['status' => false, 'message' => 'Area not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:areas,name,'.$area->id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false, 'errors'=>$validator->errors()], 422);
        }

        $area->update($request->only(['name', 'description']));

        $this->logAction("Updated area: {$area->name}");

        return response()->json(['status' => true, 'data' => $area]);
    }

    /**
     * Delete an area.
     */
    public function destroy($id)
    {
        $area = Area::find($id);

        if (!$area) {
            return response()->json(['status' => false, 'message' => 'Area not found'], 404);
        }

        $area->delete();

        $this->logAction("Deleted area: {$area->name}");

        return response()->json(['status' => true, 'message' => 'Area deleted successfully']);
    }

    /**
     * Assign supervisors and technicians to an area.
     * Payload: supervisors: [user_id], technicians: [user_id]
     */
    public function assignUsers(Request $request, $id)
    {
        $area = Area::find($id);

        if (!$area) {
            return response()->json(['status' => false, 'message' => 'Area not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'supervisors' => 'array',
            'supervisors.*' => 'exists:users,id',
            'technicians' => 'array',
            'technicians.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false, 'errors'=>$validator->errors()], 422);
        }

        // Sync supervisors and technicians
        if ($request->has('supervisors')) {
            $area->supervisors()->sync($request->input('supervisors'));
        }
        if ($request->has('technicians')) {
            $area->technicians()->sync($request->input('technicians'));
        }

        $this->logAction("Assigned users to area: {$area->name}");

        return response()->json(['status' => true, 'message' => 'Users assigned successfully']);
    }

    /**
     * Get complaints reported in this area.
     */
    public function complaints($id)
    {
        $area = Area::find($id);
        if (!$area) {
            return response()->json(['status' => false, 'message' => 'Area not found'], 404);
        }

        // Complaints associated with visits in this area
        $complaints = Complaint::whereHas('visit', function ($query) use ($id) {
            $query->where('area_id', $id);
        })->with(['visit', 'client'])->get();

        return response()->json(['status' => true, 'data' => $complaints]);
    }

    /**
     * Escalate a complaint by adding a note or changing status.
     */
    public function escalateComplaint(Request $request, $complaintId)
    {
        $complaint = Complaint::find($complaintId);

        if (!$complaint) {
            return response()->json(['status' => false, 'message' => 'Complaint not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'in:open,in_progress,resolved,escalated',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('status')) {
            $complaint->status = $request->input('status');
        }
        if ($request->has('note')) {
            $complaint->notes = $request->input('note');
        }
        $complaint->save();

        $this->logAction("Escalated complaint ID {$complaintId}");

        return response()->json(['status' => true, 'data' => $complaint]);
    }

    /**
     * Send notification to supervisors or technicians in the area.
     */
    public function notifyAreaUsers(Request $request, $id)
    {
        $area = Area::with(['supervisors', 'technicians'])->find($id);

        if (!$area) {
            return response()->json(['status' => false, 'message' => 'Area not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'user_type' => 'required|in:supervisors,technicians,both',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $message = $request->input('message');
        $userType = $request->input('user_type');

        $notifiableUsers = collect();

        if ($userType === 'supervisors' || $userType === 'both') {
            $notifiableUsers = $notifiableUsers->merge($area->supervisors);
        }
        if ($userType === 'technicians' || $userType === 'both') {
            $notifiableUsers = $notifiableUsers->merge($area->technicians);
        }

        Notification::send($notifiableUsers, new AreaNotification($message));

        $this->logAction("Sent notification to {$userType} in area {$area->name}");

        return response()->json(['status' => true, 'message' => 'Notification sent']);
    }

    /**
     * Log area manager actions for audit trail.
     */
    protected function logAction($message)
    {
        $user = auth()->user();
        Log::info("[AreaManager][UserID:{$user->id}] $message");
        // Optionally save in DB audit logs table
    }
}
