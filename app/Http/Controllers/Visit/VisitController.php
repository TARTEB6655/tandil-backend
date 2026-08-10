<?php

namespace App\Http\Controllers\Visit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area;
use App\Models\Visit;
use App\Support\CapsPagination;
use App\Support\VisitAreaResolver;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Services\JobSchedulingService;
use App\Services\VisitOfferService;
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

            $query = Visit::query()
                ->with([
                    'subscription.client:id,name,email,phone',
                    'technician:id,name,email,phone,profile_picture',
                    'supervisor:id,name,email,phone,profile_picture',
                    'area:id,name,location,country',
                    'photos:id,visit_id,photo_path,type',
                ])
                ->latest();

            $role = strtolower(trim((string) ($user->role ?? '')));

            if ($role === 'admin' || $user->hasRole('admin')) {
                // Admin sees everything
            } elseif ($role === 'area_manager' || $user->hasRole('area_manager')) {
                $areaIds = $user->supervisedAreaIds();
                $query->whereIn('area_id', $areaIds);
            } elseif ($role === 'supervisor' || $user->hasRole('supervisor')) {
                $areaIds = $user->supervisedAreaIds();
                $query->whereIn('area_id', $areaIds);
            } elseif ($role === 'technician' || $user->hasRole('technician')) {
                $query->where('technician_id', $user->id);
            } else {
                $subscriptionIds = \App\Models\Subscription::query()
                    ->where('client_id', $user->id)
                    ->pluck('id');
                $query->whereIn('subscription_id', $subscriptionIds);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('page') || $request->has('per_page')) {
                $perPage = CapsPagination::perPage($request, 20, 100);
                $paginator = $query->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'data' => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ], 200);
            }

            $visits = $query->limit(200)->get();

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

        $resolved = VisitAreaResolver::fromRequest($request);
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
     * GET /api/visits/available-slots?date=YYYY-MM-DD
     * Job Scheduling: available time slots for a date (client picks one before
     * creating the visit). Respects working hours, capacity, and blocked dates/slots.
     */
    public function availableSlots(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $date = $request->input('date');

        return response()->json([
            'status' => true,
            'message' => 'Available slots retrieved successfully.',
            'data' => [
                'date' => $date,
                'date_blocked' => JobSchedulingService::isDateFullyBlocked($date),
                'slots' => JobSchedulingService::availableSlots($date),
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
                'scheduled_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:scheduled_time',
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

            $resolved = VisitAreaResolver::fromRequest($request);
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

            // Job Scheduling: validate the chosen date/time against working hours, capacity, and blocked dates/slots.
            $schedulingError = JobSchedulingService::validateSlotForBooking($request->input('scheduled_date'), $request->input('scheduled_time'));
            if ($schedulingError) {
                return response()->json(['status' => false, 'message' => $schedulingError], 422);
            }

            // Job Scheduling: explicit duration when a custom end_time is given (Booking detail screen); else fall back to slot lookup.
            $durationMinutes = ($request->filled('scheduled_time') && $request->filled('end_time'))
                ? JobSchedulingService::minutesBetween($request->input('scheduled_time'), $request->input('end_time'))
                : null;

            // Job Scheduling: reject an explicitly-chosen technician who is already booked at this date/time.
            if ($request->filled('technician_id') && $request->filled('scheduled_time')) {
                if (JobSchedulingService::hasTechnicianConflict((int) $request->input('technician_id'), $request->input('scheduled_date'), $request->input('scheduled_time'), null, $durationMinutes)) {
                    return response()->json(['status' => false, 'message' => 'Selected technician is already booked for this date and time.'], 422);
                }
            }

            // Create visit
            $visit = Visit::create([
                'subscription_id' => $request->subscription_id,
                'technician_id' => $request->technician_id,
                'supervisor_id' => $resolvedSupervisorId,
                'area_id' => (int) $area->id,
                'scheduled_date' => $request->scheduled_date,
                'scheduled_time' => $request->input('scheduled_time'),
                'duration_minutes' => $durationMinutes,
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
                $when = $visit->scheduled_date.($visit->scheduled_time ? ' '.$visit->scheduled_time : '');

                // Notify client
                if ($visit->subscription && $visit->subscription->client) {
                    $visit->subscription->client->notify(new AdminNotification(
                        'New Visit Scheduled',
                        "A new visit has been scheduled for {$when}.",
                        ['type' => 'booking_confirmed', 'visit_id' => $visit->id, 'scheduled_date' => (string) $visit->scheduled_date, 'scheduled_time' => $visit->scheduled_time]
                    ));
                }

                // Notify technician if assigned
                if ($visit->technician_id) {
                    $technician = User::find($visit->technician_id);
                    if ($technician) {
                        $technician->notify(new AdminNotification(
                            'New Visit Assigned',
                            "You have been assigned a new visit scheduled for {$when}.",
                            ['type' => 'booking_confirmed', 'visit_id' => $visit->id, 'scheduled_date' => (string) $visit->scheduled_date, 'scheduled_time' => $visit->scheduled_time]
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
                'scheduled_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:scheduled_time',
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

            // Job Scheduling: explicit duration when a custom end_time is given (admin Booking detail screen).
            $effectiveTimeForEnd = $request->has('scheduled_time') ? $request->input('scheduled_time') : $visit->scheduled_time;
            $durationMinutes = $visit->duration_minutes;
            if ($request->filled('end_time')) {
                $durationMinutes = $effectiveTimeForEnd ? JobSchedulingService::minutesBetween($effectiveTimeForEnd, $request->input('end_time')) : null;
            }

            // Job Scheduling: re-validate the effective date/time and technician availability
            // whenever the reschedule fields (or the assigned technician) are being touched.
            if ($request->has('scheduled_date') || $request->has('scheduled_time') || $request->has('technician_id')) {
                $effectiveDate = $request->input('scheduled_date', optional($visit->scheduled_date)->toDateString());
                $effectiveTime = $request->has('scheduled_time') ? $request->input('scheduled_time') : $visit->scheduled_time;

                if ($request->has('scheduled_date') || $request->has('scheduled_time')) {
                    // Admin setting a custom end_time (Booking detail screen) isn't limited to preset slots.
                    $requireConfiguredSlot = ! ($user->hasRole('admin') && $request->filled('end_time'));
                    $schedulingError = JobSchedulingService::validateSlotForBooking($effectiveDate, $effectiveTime, $visit->id, $requireConfiguredSlot);
                    if ($schedulingError) {
                        return response()->json(['status' => false, 'message' => $schedulingError], 422);
                    }
                }

                $effectiveTechnicianId = $request->has('technician_id') ? $request->input('technician_id') : $visit->technician_id;
                if ($effectiveTechnicianId && JobSchedulingService::hasTechnicianConflict((int) $effectiveTechnicianId, $effectiveDate, $effectiveTime, $visit->id, $durationMinutes)) {
                    return response()->json(['status' => false, 'message' => 'Selected technician is already booked for this date and time.'], 422);
                }
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
            if ($request->has('scheduled_time')) {
                $visit->scheduled_time = $request->input('scheduled_time');
            }
            if ($request->has('end_time')) {
                $visit->duration_minutes = $durationMinutes;
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
            $oldScheduledDate = $visit->getOriginal('scheduled_date');
            $oldScheduledDate = $oldScheduledDate instanceof \Carbon\Carbon ? $oldScheduledDate->toDateString() : $oldScheduledDate;
            $oldScheduledTime = $visit->getOriginal('scheduled_time');
            $visit->save();

            // 🔔 Job Scheduling: notify on reschedule (date/time changed) or other field updates.
            try {
                $newScheduledDate = optional($visit->scheduled_date)->toDateString();
                $rescheduled = ($request->has('scheduled_date') && $oldScheduledDate !== $newScheduledDate)
                    || ($request->has('scheduled_time') && $oldScheduledTime !== $visit->scheduled_time);

                if ($rescheduled) {
                    $when = $newScheduledDate.($visit->scheduled_time ? ' '.$visit->scheduled_time : '');
                    $meta = ['type' => 'booking_rescheduled', 'visit_id' => $visit->id, 'scheduled_date' => $newScheduledDate, 'scheduled_time' => $visit->scheduled_time];

                    if ($visit->subscription && $visit->subscription->client) {
                        $visit->subscription->client->notify(new AdminNotification('Visit Rescheduled', "Your visit has been rescheduled to {$when}.", $meta));
                    }
                    if ($visit->technician_id) {
                        User::find($visit->technician_id)?->notify(new AdminNotification('Visit Rescheduled', "Visit #{$visit->id} has been rescheduled to {$when}.", $meta));
                    }
                } elseif ($request->has('price') || $request->has('notes') || $request->has('technician_id') || $request->has('supervisor_id') || $request->has('area_id')) {
                    $meta = ['type' => 'booking_updated', 'visit_id' => $visit->id];

                    if ($visit->subscription && $visit->subscription->client) {
                        $visit->subscription->client->notify(new AdminNotification('Visit Updated', "Your visit #{$visit->id} details have been updated.", $meta));
                    }
                    if ($visit->technician_id) {
                        User::find($visit->technician_id)?->notify(new AdminNotification('Visit Updated', "Visit #{$visit->id} details have been updated.", $meta));
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send visit reschedule/update notifications: ' . $e->getMessage());
            }

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
