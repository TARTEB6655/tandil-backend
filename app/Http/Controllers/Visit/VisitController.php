<?php

namespace App\Http\Controllers\Visit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:client|technician|supervisor|area_manager|admin');
    }

    /**
     * List visits based on role:
     * - Admin: All visits
     * - Area Manager: Visits in managed areas
     * - Supervisor: Visits in supervised areas
     * - Technician: Assigned visits
     * - Client: Visits of their subscriptions
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
            }

            if ($user->hasRole('admin')) {
                // Admin sees everything
                $visits = Visit::with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } elseif ($user->hasRole('area_manager')) {
                // Area Manager → visits in their managed areas
                $areaIds = $user->supervisedAreaIds();

                $visits = Visit::whereIn('area_id', $areaIds)
                    ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } elseif ($user->hasRole('supervisor')) {
                // Supervisor → visits in areas they supervise
                $areaIds = $user->supervisedAreaIds();

                $visits = Visit::whereIn('area_id', $areaIds)
                    ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } elseif ($user->hasRole('technician')) {
                // Technician → assigned visits
                $visits = Visit::where('technician_id', $user->id)
                    ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                    ->latest()
                    ->get();
            } else {
                // Client → their subscription visits
                $visits = Visit::whereHas('subscription', function ($q) use ($user) {
                    $q->where('client_id', $user->id);
                })
                ->with(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])
                ->latest()
                ->get();
            }

            return response()->json(['status' => true, 'data' => $visits], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch visits: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new visit
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            // Validation
            $validator = Validator::make($request->all(), [
                'subscription_id' => 'required|exists:subscriptions,id',
                'technician_id' => 'nullable|exists:users,id',
                'supervisor_id' => 'nullable|exists:users,id',
                'area_id' => 'nullable|exists:areas,id',
                'scheduled_date' => 'required|date',
                'status' => 'nullable|string|in:pending,scheduled,in_progress,completed,approved,rejected',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Authorization: Only admin or client (for their own subscription) can create visits
            $subscription = \App\Models\Subscription::find($request->subscription_id);
            
            if (!$subscription) {
                return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
            }

            // Check if user is admin or owns the subscription
            if (!$user->hasRole('admin') && $subscription->client_id !== $user->id) {
                return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
            }

            // Create visit
            $visit = Visit::create([
                'subscription_id' => $request->subscription_id,
                'technician_id' => $request->technician_id,
                'supervisor_id' => $request->supervisor_id,
                'area_id' => $request->area_id,
                'scheduled_date' => $request->scheduled_date,
                'status' => $request->status ?? 'pending',
            ]);

            // Load relationships
            $visit->load(['subscription.client', 'technician', 'supervisor', 'area', 'photos']);

            // 🔔 Send notifications
            try {
                // Notify client
                if ($visit->subscription && $visit->subscription->client) {
                    $visit->subscription->client->notify(new AdminNotification(
                        'New Visit Scheduled',
                        "A new visit has been scheduled for {$visit->scheduled_date}."
                    ));
                }

                // Notify technician if assigned
                if ($visit->technician_id) {
                    $technician = User::find($visit->technician_id);
                    if ($technician) {
                        $technician->notify(new AdminNotification(
                            'New Visit Assigned',
                            "You have been assigned a new visit scheduled for {$visit->scheduled_date}."
                        ));
                    }
                }

                // Notify supervisor if assigned
                if ($visit->supervisor_id) {
                    $supervisor = User::find($visit->supervisor_id);
                    if ($supervisor) {
                        $supervisor->notify(new AdminNotification(
                            'New Visit Created',
                            "A new visit has been created in your area, scheduled for {$visit->scheduled_date}."
                        ));
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send visit creation notifications: ' . $e->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Visit created successfully',
                'data' => $visit
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create visit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show details of a visit
     */
    public function show(Request $request, $id)
    {
        $visit = Visit::with(['subscription.client', 'technician', 'supervisor', 'area', 'photos', 'report'])
            ->find($id);

        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $user = $request->user();

        // Authorization rules
        if (
            $user->hasRole('admin') ||
            ($user->hasRole('technician') && $visit->technician_id === $user->id) ||
            ($user->hasRole('client') && $visit->subscription->client_id === $user->id) ||
            ($user->hasRole('supervisor') && in_array($visit->area_id, $user->supervisedAreaIds())) ||
            ($user->hasRole('area_manager') && in_array($visit->area_id, $user->supervisedAreaIds()))
        ) {
            return response()->json(['status' => true, 'data' => $visit], 200);
        }

        return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
    }

    /**
     * Upload a photo (admin, assigned technician, or client for own visits)
     */
    public function uploadPhoto(Request $request, $id)
    {
        $visit = Visit::with('subscription')->find($id);
        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $user = $request->user();

        // Authorization: admin, assigned technician, or client (own subscription)
        $isAuthorized = false;

        if ($user->hasRole('admin')) {
            $isAuthorized = true;
        } elseif ($user->hasRole('technician') && $visit->technician_id == $user->id) {
            // Technician can upload if assigned to the visit
            $isAuthorized = true;
        } elseif ($user->hasRole('client') && $visit->subscription && $visit->subscription->client_id === $user->id) {
            // Client can upload photos for their own visits
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:5120',
            'type'  => 'nullable|string|in:before,after',
        ], [
            'photo.required' => 'Please select an image file to upload.',
            'photo.image' => 'The uploaded file must be an image.',
            'photo.max' => 'The image size must not exceed 5MB.',
            'type.in' => 'The type must be either "before" or "after".',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Upload
        $path = $request->file('photo')->store('visit_photos', 'public');

        $photo = VisitPhoto::create([
            'visit_id'   => $visit->id,
            'type'       => $request->input('type', 'before'),
            'photo_path' => $path,
        ]);

        return response()->json(['status' => true, 'data' => $photo], 201);
    }

    /**
     * Update visit (general update)
     */
    public function update(Request $request, $id)
    {
        try {
            $visit = Visit::with('subscription')->find($id);
            if (!$visit) {
                return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
            }

            $user = $request->user();

            // Authorization: Admin, client (own subscription), technician (assigned), supervisor/area_manager (area supervised)
            $isAuthorized = false;

            if ($user->hasRole('admin')) {
                $isAuthorized = true;
            } elseif ($user->hasRole('client') && $visit->subscription && $visit->subscription->client_id === $user->id) {
                // Client can update their own visits
                $isAuthorized = true;
            } elseif ($user->hasRole('technician') && $visit->technician_id === $user->id) {
                // Technician can update if assigned
                $isAuthorized = true;
            } elseif ($user->hasRole('supervisor') || $user->hasRole('area_manager')) {
                // Supervisor/Area Manager can update if area is supervised
                if ($visit->area_id) {
                    $areaIds = $user->supervisedAreaIds();
                    if (in_array($visit->area_id, $areaIds)) {
                        $isAuthorized = true;
                    }
                }
            }

            if (!$isAuthorized) {
                return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
            }

            $validator = Validator::make($request->all(), [
                'scheduled_date' => 'nullable|date',
                'notes' => 'nullable|string|max:2000',
                'status' => 'nullable|string|in:pending,scheduled,in_progress,completed,approved,rejected',
                'technician_id' => 'nullable|exists:users,id',
                'supervisor_id' => 'nullable|exists:users,id',
                'area_id' => 'nullable|exists:areas,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
            }

            // Role-based status restrictions
            if ($request->has('status')) {
                $newStatus = $request->input('status');
                
                // Clients can only set: pending, scheduled
                if ($user->hasRole('client') && !in_array($newStatus, ['pending', 'scheduled'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Clients can only set status to pending or scheduled'
                    ], 403);
                }
                
                // Technicians can only set: in_progress, completed
                if ($user->hasRole('technician') && !in_array($newStatus, ['in_progress', 'completed'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Technicians can only set status to in_progress or completed'
                    ], 403);
                }
                
                // Supervisors can only set: approved, rejected
                if ($user->hasRole('supervisor') && !in_array($newStatus, ['approved', 'rejected'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Supervisors can only set status to approved or rejected'
                    ], 403);
                }
                
                $visit->status = $newStatus;
            }

            if ($request->has('scheduled_date')) {
                $visit->scheduled_date = $request->input('scheduled_date');
            }
            if ($request->has('notes')) {
                $visit->notes = $request->input('notes');
            }
            
            // Allow admin and client (own subscription) to assign technician/supervisor/area
            if ($user->hasRole('admin') || ($user->hasRole('client') && $visit->subscription && $visit->subscription->client_id === $user->id)) {
                if ($request->has('technician_id')) {
                    $visit->technician_id = $request->input('technician_id');
                }
                if ($request->has('supervisor_id')) {
                    $visit->supervisor_id = $request->input('supervisor_id');
                }
                if ($request->has('area_id')) {
                    $visit->area_id = $request->input('area_id');
                }
            }

            $oldStatus = $visit->getOriginal('status');
            $visit->save();

            // 🔔 Send notifications based on status change
            try {
                if ($request->has('status') && $oldStatus !== $visit->status) {
                    // Notify client when visit status changes
                    if ($visit->subscription && $visit->subscription->client) {
                        $visit->subscription->client->notify(new AdminNotification(
                            'Visit Status Updated',
                            "Your visit scheduled for {$visit->scheduled_date} status has been changed to: " . ucfirst(str_replace('_', ' ', $visit->status))
                        ));
                    }

                    // Notify technician if status is completed
                    if ($visit->status === 'completed' && $visit->technician_id) {
                        $technician = User::find($visit->technician_id);
                        if ($technician) {
                            $technician->notify(new AdminNotification(
                                'Visit Completed',
                                "Visit #{$visit->id} has been marked as completed."
                            ));
                        }
                    }

                    // Notify supervisor when visit needs approval
                    if ($visit->status === 'completed' && $visit->supervisor_id) {
                        $supervisor = User::find($visit->supervisor_id);
                        if ($supervisor) {
                            $supervisor->notify(new AdminNotification(
                                'Visit Awaiting Approval',
                                "Visit #{$visit->id} has been completed and is awaiting your approval."
                            ));
                        }
                    }
                }

                // Notify technician if assigned
                if ($request->has('technician_id') && $visit->technician_id) {
                    $technician = User::find($visit->technician_id);
                    if ($technician) {
                        $technician->notify(new AdminNotification(
                            'Visit Assigned to You',
                            "You have been assigned to visit #{$visit->id} scheduled for {$visit->scheduled_date}."
                        ));
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send visit update notifications: ' . $e->getMessage());
            }

            return response()->json(['status' => true, 'data' => $visit->load(['subscription.client', 'technician', 'supervisor', 'area', 'photos'])], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update visit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update visit status
     * - Technician marks as in_progress/completed
     * - Supervisor approves
     * - Admin can change anything
     */
    public function updateStatus(Request $request, $id)
    {
        $visit = Visit::find($id);
        if (!$visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,in_progress,completed,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $status = $request->status;

        // Permissions
        if ($user->hasRole('technician') && $visit->technician_id !== $user->id) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        if ($user->hasRole('supervisor')) {
            $areaIds = $user->supervisedAreaIds();
            if (!in_array($visit->area_id, $areaIds)) {
                return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
            }
        }

        if ($user->hasRole('client')) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        // Technician can only mark in_progress or completed
        if ($user->hasRole('technician') && !in_array($status, ['in_progress', 'completed'])) {
            return response()->json(['status' => false, 'message' => 'Technician cannot set this status'], 403);
        }

        // Supervisor can approve/reject
        if ($user->hasRole('supervisor') && !in_array($status, ['approved', 'rejected'])) {
            return response()->json(['status' => false, 'message' => 'Supervisor can only approve/reject'], 403);
        }

        // Save status
        $oldStatus = $visit->status;
        $visit->status = $status;

        if ($status === 'completed') {
            $visit->completed_date = now();
        }

        $visit->save();

        // 🔔 Send notifications based on status change
        try {
            // Notify client when visit is completed
            if ($status === 'completed' && $oldStatus !== 'completed') {
                if ($visit->subscription && $visit->subscription->client) {
                    $visit->subscription->client->notify(new AdminNotification(
                        'Visit Completed',
                        "Your visit scheduled for {$visit->scheduled_date} has been completed. Thank you!"
                    ));
                }

                // Notify supervisor for approval
                if ($visit->supervisor_id) {
                    $supervisor = User::find($visit->supervisor_id);
                    if ($supervisor) {
                        $supervisor->notify(new AdminNotification(
                            'Visit Completed - Awaiting Approval',
                            "Visit #{$visit->id} has been completed and requires your approval."
                        ));
                    }
                }
            }

            // Notify when visit is approved
            if ($status === 'approved' && $oldStatus !== 'approved') {
                if ($visit->subscription && $visit->subscription->client) {
                    $visit->subscription->client->notify(new AdminNotification(
                        'Visit Approved',
                        "Your visit #{$visit->id} has been approved by the supervisor."
                    ));
                }
            }

            // Notify when visit is rejected
            if ($status === 'rejected' && $oldStatus !== 'rejected') {
                if ($visit->technician_id) {
                    $technician = User::find($visit->technician_id);
                    if ($technician) {
                        $technician->notify(new AdminNotification(
                            'Visit Rejected',
                            "Visit #{$visit->id} has been rejected. Please review and resubmit."
                        ));
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send visit status notifications: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'message' => 'Status updated', 'data' => $visit], 200);
    }
}
