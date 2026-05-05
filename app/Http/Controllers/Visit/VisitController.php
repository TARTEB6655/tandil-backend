<?php

namespace App\Http\Controllers\Visit;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ParsesMultipartPhoto;
use Illuminate\Http\Request;
use App\Models\Area;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Services\VisitOfferService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    use ParsesMultipartPhoto;

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
     * GET /api/visits/areas
     * List areas (for visit creation). Client/admin use this to pick area_id when creating a visit.
     * Optional ?with=supervisors to include supervisor(s) per area so client knows who oversees the area.
     */
    public function areas(Request $request)
    {
        $withSupervisors = $request->query('with') === 'supervisors';
        $includeInactive = $request->boolean('include_inactive', false) && $request->user()?->hasRole('admin');
        $areas = Area::query()
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->when($withSupervisors, fn ($q) => $q->with('supervisors'))
            ->orderBy('name')
            ->get();

        $data = $areas->map(function ($area) use ($withSupervisors) {
            $item = [
                'id' => $area->id,
                'name' => $area->name,
                'description' => $area->description,
                'country' => $area->country ?? 'UAE',
                'location' => $area->location,
                'is_active' => (bool) ($area->is_active ?? true),
                'priority' => (int) ($area->priority ?? 100),
                'latitude' => $area->latitude !== null ? (float) $area->latitude : null,
                'longitude' => $area->longitude !== null ? (float) $area->longitude : null,
                'service_radius_km' => $area->service_radius_km !== null ? (float) $area->service_radius_km : null,
            ];
            if ($withSupervisors && $area->relationLoaded('supervisors')) {
                $item['supervisors'] = $area->supervisors->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ])->values()->toArray();
            }
            return $item;
        });

        return response()->json([
            'status' => true,
            'message' => 'Areas retrieved successfully. Use area id when creating a visit so the job reaches the supervisor for that area.',
            'data' => $data,
        ], 200);
    }

    /**
     * Resolve area + supervisor from city/address/GPS before creating a visit.
     */
    public function resolveArea(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'area_id' => 'nullable|exists:areas,id',
            'full_name' => 'nullable|string|max:255',
            'street_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $resolved = $this->resolveAreaFromRequest($request);
        if (! $resolved) {
            return response()->json([
                'status' => false,
                'serviceable' => false,
                'message' => 'Service is currently unavailable in this area. Please choose another location.',
            ], 422);
        }

        return response()->json([
            'status' => true,
            'serviceable' => true,
            'message' => 'Area resolved successfully.',
            'data' => [
                'area_id' => (int) $resolved['area']->id,
                'area_name' => $resolved['area']->name,
                'supervisor_id' => (int) $resolved['supervisor_id'],
                'distance_km' => $resolved['distance_km'],
            ],
        ], 200);
    }

    /**
     * Create a new visit
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            // Validation (accepts both JSON and form-data)
            $validator = Validator::make($request->all(), [
                'subscription_id' => 'required|exists:subscriptions,id',
                'technician_id' => 'nullable|exists:users,id',
                'supervisor_id' => 'nullable|exists:users,id',
                'area_id' => 'nullable|exists:areas,id',
                'full_name' => 'nullable|string|max:255',
                'street_address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:255',
                'state' => 'nullable|string|max:255',
                'zip_code' => 'nullable|string|max:30',
                'country' => 'nullable|string|max:100',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'scheduled_date' => 'required|date',
                'status' => 'nullable|string|in:pending,scheduled,in_progress,completed,approved,rejected',
                'notes' => 'nullable|string|max:5000',
                'price' => 'nullable|numeric|min:0',
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

            $resolved = $this->resolveAreaFromRequest($request);
            if (! $resolved) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to resolve area from selected location. Send area_id or provide app location fields (street_address/city/state/zip_code/country) or GPS coordinates.',
                ], 422);
            }
            $area = $resolved['area'];

            $resolvedSupervisorId = $request->filled('supervisor_id')
                ? (int) $request->input('supervisor_id')
                : (int) $resolved['supervisor_id'];
            if (! $resolvedSupervisorId) {
                return response()->json([
                    'status' => false,
                    'message' => 'No supervisor assigned to selected area. Ask admin to map a supervisor for this area first.',
                ], 422);
            }

            // Create visit
            $visit = Visit::create([
                'subscription_id' => $request->subscription_id,
                'technician_id' => $request->technician_id,
                'supervisor_id' => $resolvedSupervisorId,
                'area_id' => (int) $area->id,
                'scheduled_date' => $request->scheduled_date,
                'status' => $request->status ?? 'pending',
                'notes' => $request->input('notes'),
                'price' => $request->filled('price') ? (float) $request->input('price') : null,
            ]);

            // Smart Auto-Dispatch: when client order has zone (area_id) and no pre-assigned technician, offer to first available technician in zone or escalate to supervisor
            if ($visit->area_id && ! $visit->technician_id) {
                try {
                    $next = VisitOfferService::findNextTechnician($visit);
                    if ($next) {
                        VisitOfferService::offerToTechnician($visit, $next->id);
                    } else {
                        $visit->escalated_at = now();
                        $visit->status = 'pending';
                        $visit->save();
                    }
                } catch (\Exception $e) {
                    \Log::error('Auto-dispatch on visit create failed: ' . $e->getMessage());
                }
            }

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

                // Notify area supervisors when job escalated (no technicians in zone – needs manual assignment)
                if ($visit->escalated_at && $visit->area_id) {
                    $area = Area::with('supervisors')->find($visit->area_id);
                    if ($area && $area->supervisors->isNotEmpty()) {
                        foreach ($area->supervisors as $sup) {
                            $sup->notify(new AdminNotification(
                                'Job Escalated – Manual Assignment Needed',
                                "A visit scheduled for {$visit->scheduled_date} has been escalated to you (no available technician in zone). Assign via Supervisor dashboard."
                            ));
                        }
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
     * Upload a photo (admin, assigned technician, or client for own visits).
     * Supports POST and PUT with multipart/form-data (same as category image update) so image upload works
     * when PHP does not populate $_FILES (e.g. PUT requests or some proxies). Field name: "photo"; optional: "type" (before|after).
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

        // Parse multipart body for PUT/POST so "photo" file is available (same as category image update)
        $contentType = $request->header('Content-Type', '');
        if (str_contains($contentType, 'multipart/form-data') && ($request->isMethod('PUT') || $request->isMethod('POST'))) {
            $this->parseMultipartIntoRequest($request, 'photo');
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image',
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
        \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($path);

        $photo = VisitPhoto::create([
            'visit_id'   => $visit->id,
            'type'       => $request->input('type', 'before'),
            'photo_path' => $path,
        ]);

        // Response shape aligned with category image update: include photo_url for client use
        $photoUrl = $path ? (request()->getSchemeAndHttpHost() ? rtrim(request()->getSchemeAndHttpHost(), '/') . '/storage/' . $path : asset('storage/' . $path)) : null;
        $data = $photo->toArray();
        $data['photo_url'] = $photoUrl;

        return response()->json(['status' => true, 'data' => $data], 201);
    }

    /**
     * Delete a maintenance/visit photo.
     * Who can delete:
     * - Admin: can delete any photo.
     * - Technician: can delete only photos of visits assigned to them.
     * - Client: can delete only photos of their own visits (their subscription).
     */
    public function deletePhoto(Request $request, $visitId, $photoId)
    {
        $visit = Visit::with('subscription')->find($visitId);
        if (! $visit) {
            return response()->json(['status' => false, 'message' => 'Visit not found'], 404);
        }

        $photo = VisitPhoto::where('visit_id', $visitId)->where('id', $photoId)->first();
        if (! $photo) {
            return response()->json(['status' => false, 'message' => 'Photo not found'], 404);
        }

        $user = $request->user();
        $isAuthorized = false;

        if ($user->hasRole('admin')) {
            $isAuthorized = true;
        } elseif ($user->hasRole('technician') && $visit->technician_id == $user->id) {
            $isAuthorized = true;
        } elseif ($user->hasRole('client') && $visit->subscription && $visit->subscription->client_id === $user->id) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            return response()->json(['status' => false, 'message' => 'You are not allowed to delete this photo'], 403);
        }

        if ($photo->photo_path && Storage::disk('public')->exists($photo->photo_path)) {
            Storage::disk('public')->delete($photo->photo_path);
        }
        $photo->delete();

        return response()->json(['status' => true, 'message' => 'Photo deleted successfully'], 200);
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
                'notes' => 'nullable|string|max:5000',
                'price' => 'nullable|numeric|min:0',
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
            if ($request->has('price')) {
                $visit->price = $request->filled('price') ? (float) $request->input('price') : null;
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

    private function resolveAreaFromRequest(Request $request): ?array
    {
        if ($request->filled('area_id')) {
            $area = Area::with('supervisors')
                ->where('is_active', true)
                ->find((int) $request->input('area_id'));
            if ($area && $area->supervisors->isNotEmpty()) {
                return [
                    'area' => $area,
                    'supervisor_id' => (int) $area->supervisors->first()->id,
                    'distance_km' => null,
                ];
            }
        }

        $country = strtolower(trim((string) $request->input('country', 'UAE')));
        $city = strtolower(trim((string) $request->input('city', '')));
        $state = strtolower(trim((string) $request->input('state', '')));
        $streetAddress = strtolower(trim((string) $request->input('street_address', '')));
        $zipCode = strtolower(trim((string) $request->input('zip_code', '')));
        $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $lng = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        $areas = Area::with('supervisors')
            ->where('is_active', true)
            ->when($country !== '', fn ($q) => $q->whereRaw('LOWER(country) = ?', [$country]))
            ->get()
            ->filter(fn (Area $a) => $a->supervisors->isNotEmpty())
            ->values();

        if ($areas->isEmpty()) {
            return null;
        }

        $nameMatched = $areas->filter(function (Area $area) use ($city, $state, $streetAddress, $zipCode) {
            if ($city === '' && $state === '' && $streetAddress === '' && $zipCode === '') {
                return false;
            }
            $hay = strtolower(trim((string) ($area->name . ' ' . ($area->location ?? '') . ' ' . ($area->description ?? ''))));

            return ($city !== '' && str_contains($hay, $city))
                || ($state !== '' && str_contains($hay, $state))
                || ($streetAddress !== '' && str_contains($hay, $streetAddress))
                || ($zipCode !== '' && str_contains($hay, $zipCode));
        })->sortBy('priority')->values();

        if ($nameMatched->isNotEmpty()) {
            $selected = $nameMatched->first();
            return [
                'area' => $selected,
                'supervisor_id' => (int) $selected->supervisors->first()->id,
                'distance_km' => null,
            ];
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        $closest = null;
        foreach ($areas as $area) {
            if ($area->latitude === null || $area->longitude === null) {
                continue;
            }

            $distance = $this->distanceKm($lat, $lng, (float) $area->latitude, (float) $area->longitude);
            $radius = max(0.1, (float) ($area->service_radius_km ?? 30));
            if ($distance > $radius) {
                continue;
            }

            if ($closest === null
                || $distance < $closest['distance_km']
                || ($distance === $closest['distance_km'] && (int) $area->priority < (int) $closest['area']->priority)
            ) {
                $closest = [
                    'area' => $area,
                    'supervisor_id' => (int) $area->supervisors->first()->id,
                    'distance_km' => round($distance, 2),
                ];
            }
        }

        return $closest;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
