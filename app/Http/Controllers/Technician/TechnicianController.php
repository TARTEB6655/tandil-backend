<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadVisitPhotoRequest;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\Complaint;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VisitAssignedNotification;

class TechnicianController extends Controller
{
    public function __construct()
    {
        // Middleware is handled in routes, but we can add auth here for safety
        $this->middleware('auth:sanctum');
    }

    // List all visits assigned to the technician
    public function assigned(Request $request)
    {
        $user = $request->user();

        $visits = Visit::where('technician_id', $user->id)
            ->with('subscription.client')
            ->get();

        return response()->json(['status' => true, 'data' => $visits], 200);
    }

    // (Optional) List visits under technician's areas (if areas assigned)
    public function visitsByArea(Request $request, $areaId)
    {
        $user = $request->user();

        // You may add logic to confirm the technician belongs to this area
        $visits = Visit::where('area_id', $areaId)
            ->where('technician_id', $user->id)
            ->with('subscription.client')
            ->get();

        return response()->json(['status' => true, 'data' => $visits], 200);
    }

    public function accept(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }
        if ($visit->technician_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }
        $visit->status = 'accepted';
        $visit->accepted_at = now();
        $visit->save();

        // 🔔 Send notifications
        try {
            // Notify client
            if ($visit->subscription && $visit->subscription->client) {
                $visit->subscription->client->notify(new AdminNotification(
                    'Visit Accepted',
                    "The technician has accepted your visit scheduled for {$visit->scheduled_date}."
                ));
            }

            // Notify supervisor
            if ($visit->supervisor_id) {
                $supervisor = User::find($visit->supervisor_id);
                if ($supervisor) {
                    $supervisor->notify(new AdminNotification(
                        'Visit Accepted by Technician',
                        "Visit #{$visit->id} has been accepted by the assigned technician."
                    ));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send visit acceptance notification: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'data' => $visit], 200);
    }

    public function start(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }
        if ($visit->technician_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }
        $visit->status = 'in_progress';
        $visit->started_at = now();
        $visit->save();

        // 🔔 Notify client that visit has started
        try {
            if ($visit->subscription && $visit->subscription->client) {
                $visit->subscription->client->notify(new AdminNotification(
                    'Visit Started',
                    "The technician has started your visit scheduled for {$visit->scheduled_date}."
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send visit start notification: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'data' => $visit], 200);
    }

    public function updateNotes(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }
        if ($visit->technician_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $visit->notes = $request->input('notes');
        $visit->save();

        return response()->json(['status' => true, 'data' => $visit], 200);
    }

    public function complete(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }
        if ($visit->technician_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $visit->status = 'completed';
        $visit->completed_at = now();
        $visit->notes = $request->input('notes', $visit->notes);
        $visit->save();

        // 🔔 Send notifications
        try {
            // Notify client
            if ($visit->subscription && $visit->subscription->client) {
                $visit->subscription->client->notify(new AdminNotification(
                    'Visit Completed',
                    "Your visit scheduled for {$visit->scheduled_date} has been completed by the technician."
                ));
            }

            // Notify supervisor for approval
            if ($visit->supervisor_id) {
                $supervisor = User::find($visit->supervisor_id);
                if ($supervisor) {
                    $supervisor->notify(new AdminNotification(
                        'Visit Completed - Awaiting Approval',
                        "Visit #{$visit->id} has been completed and is awaiting your approval."
                    ));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send visit completion notification: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'data' => $visit], 200);
    }

    public function uploadPhoto(UploadVisitPhotoRequest $request, $id)
    {
        $visit = Visit::find($id);
        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }
        if ($visit->technician_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $file = $request->file('photo');
        $type = $request->input('type', 'after');
        $path = $file->store('visit_photos', 'public');

        $vp = VisitPhoto::create([
            'visit_id' => $visit->id,
            'photo_path' => $path,
            'type' => $type,
        ]);

        return response()->json(['status' => true, 'data' => $vp], 201);
    }

    // List complaints related to technician's visits
    public function complaints(Request $request)
    {
        $user = $request->user();

        $complaints = Complaint::whereHas('visit', function ($query) use ($user) {
            $query->where('technician_id', $user->id);
        })->with(['visit', 'client'])->get();

        return response()->json(['status' => true, 'data' => $complaints], 200);
    }

    // Escalate a complaint by technician
    public function escalateComplaint(Request $request, $complaintId)
    {
        $complaint = Complaint::find($complaintId);

        if (! $complaint) {
            return response()->json(['status' => false, 'message' => 'Complaint not found'], 404);
        }

        // Verify that this complaint belongs to a visit assigned to this technician
        if ($complaint->visit->technician_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
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

        return response()->json(['status' => true, 'data' => $complaint], 200);
    }
}
