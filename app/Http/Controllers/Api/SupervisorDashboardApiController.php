<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminReport;
use App\Models\Report;
use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use App\Models\Visit;
use App\Models\TechnicianBreak;
use App\Models\Area;
use App\Models\Complaint;
use App\Services\VisitOfferService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupervisorDashboardApiController extends Controller
{
    private function areaIds(Request $request): array
    {
        return $request->user()->supervisedAreaIds();
    }

    private function visitsQuery(Request $request)
    {
        $areaIds = $this->areaIds($request);
        $supervisorId = $request->user()->id;
        return Visit::query()->where(function ($q) use ($areaIds, $supervisorId) {
            if (! empty($areaIds)) {
                $q->whereIn('area_id', $areaIds);
            }
            $q->orWhere('supervisor_id', $supervisorId);
        });
    }

    /** Visits for a specific technician that this supervisor can see (in my areas OR assigned by me). */
    private function memberVisitsQuery(Request $request, int $technicianId)
    {
        $areaIds = $this->areaIds($request);
        $supervisorId = $request->user()->id;
        return Visit::query()
            ->where('technician_id', $technicianId)
            ->where(function ($q) use ($areaIds, $supervisorId) {
                if (! empty($areaIds)) {
                    $q->whereIn('area_id', $areaIds);
                }
                $q->orWhere('supervisor_id', $supervisorId);
            });
    }

    /** Visits that need supervisor action: unassigned, pending/scheduled, or escalated after auto-dispatch failures. */
    private function assignableVisitsQuery(Request $request)
    {
        return $this->visitsQuery($request)->where(function ($q) {
            $q->whereNull('technician_id')
                ->orWhereIn('status', ['pending', 'scheduled'])
                ->orWhereNotNull('escalated_at');
        });
    }

    /** Visits the supervisor can update or reassign: assignable list + already assigned (pending_acceptance, in_progress). */
    private function editableAssignmentVisitsQuery(Request $request)
    {
        return $this->visitsQuery($request)->where(function ($q) {
            $q->whereNull('technician_id')
                ->orWhereIn('status', ['pending', 'scheduled', 'pending_acceptance', 'in_progress'])
                ->orWhereNotNull('escalated_at');
        });
    }

    /** Visit IDs the supervisor can see reports for: visits in their zones + visits assigned to them (supervisor_id = me). */
    private function reportVisitIds(Request $request): \Illuminate\Support\Collection
    {
        $supervisorId = $request->user()->id;
        $fromAreas = $this->visitsQuery($request)->pluck('id');
        $assignedToMe = Visit::where('supervisor_id', $supervisorId)->pluck('id');
        return $fromAreas->merge($assignedToMe)->unique()->values();
    }

    /** Reports the supervisor can see: report.supervisor_id = me (technician sent to me) OR visit in my scope. */
    private function reportsForSupervisorQuery(Request $request)
    {
        $supervisorId = $request->user()->id;
        $visitIds = $this->reportVisitIds($request);
        return Report::where(function ($q) use ($supervisorId, $visitIds) {
            $q->where('supervisor_id', $supervisorId);
            if ($visitIds->isNotEmpty()) {
                $q->orWhereIn('visit_id', $visitIds);
            }
        });
    }

    /**
     * Single dashboard API: profile (picture, name, id) + 3 counts only (team_members, active_visits, completed_visits). Nothing else.
     */
    public function dashboardSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee');

        $query = $this->visitsQuery($request);
        $areaIds = $this->areaIds($request);

        $teamMembersCount = $this->teamMemberIdsInZones($areaIds)->count();

        $activeVisitsCount = (clone $query)
            ->whereIn('status', ['pending', 'scheduled', 'in_progress'])
            ->count();

        $completedVisitsCount = (clone $query)
            ->whereIn('status', ['completed', 'approved'])
            ->count();

        $escalatedJobsCount = $this->assignableVisitsQuery($request)->whereNotNull('escalated_at')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
                'name' => $user->name,
                'id' => $user->employee?->employee_id ?? ('SUP-' . $user->id),
                'team_members' => $teamMembersCount,
                'active_visits' => $activeVisitsCount,
                'completed_visits' => $completedVisitsCount,
                'escalated_jobs' => $escalatedJobsCount,
            ],
        ]);
    }

    public function dashboardKpis(Request $request): JsonResponse
    {
        $query = $this->visitsQuery($request);
        $today = Carbon::today();

        $completedToday = (clone $query)->whereDate('completed_at', $today)->count();
        $completionRate = (clone $query)->count() > 0
            ? round(((clone $query)->where('status', 'completed')->count() / (clone $query)->count()) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'completed_today' => $completedToday,
                'completion_rate_percent' => $completionRate,
                'avg_response_minutes' => 0, // placeholder KPI
                'open_complaints' => 0, // can be replaced with complaint aggregation if needed
            ],
        ]);
    }

    public function dashboardAlerts(Request $request): JsonResponse
    {
        $alerts = [];
        $tomorrow = Carbon::tomorrow();

        $overdue = $this->visitsQuery($request)
            ->whereDate('scheduled_date', '<', Carbon::today())
            ->whereNotIn('status', ['completed', 'approved', 'rejected'])
            ->count();
        if ($overdue > 0) {
            $alerts[] = ['type' => 'overdue_visits', 'count' => $overdue, 'message' => "{$overdue} visits are overdue."];
        }

        $upcoming = $this->visitsQuery($request)
            ->whereDate('scheduled_date', $tomorrow)
            ->count();
        if ($upcoming > 0) {
            $alerts[] = ['type' => 'upcoming_visits', 'count' => $upcoming, 'message' => "{$upcoming} visits scheduled for tomorrow."];
        }

        return response()->json(['success' => true, 'data' => $alerts]);
    }

    /**
     * Technicians linked to supervisor's zones at setup (area_technician). Client: "Technicians are linked to a Supervisor during setup."
     */
    private function teamMemberIdsInZones(array $areaIds): \Illuminate\Support\Collection
    {
        if (empty($areaIds)) {
            return collect();
        }
        return collect(DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id'));
    }

    /**
     * Single "My Team" API: technicians linked to supervisor's zones at setup (area_technician).
     * Returns: name, employee_id, status (Active/Break), current_activity, tasks (completed/total).
     * Empty data: supervisor has no assigned zones (area_supervisor), or zones have no technicians (area_technician). Assign via Admin Areas and Admin Supervisor Team.
     */
    public function myTeam(Request $request): JsonResponse
    {
        $areaIds = $this->areaIds($request);
        $now = Carbon::now();
        $today = $now->toDateString();

        if (empty($areaIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No zones assigned to you. Ask admin to assign you to areas (Admin Areas).',
            ]);
        }

        $technicianIds = $this->teamMemberIdsInZones($areaIds);
        if ($technicianIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No team members in your zones. Ask admin to add technicians to your areas (Admin Supervisor Team).',
            ]);
        }

        // Include all team members (active + inactive) so supervisor/AM/admin see status; assign flow still uses active() only
        $technicians = User::role('technician')
            ->whereIn('id', $technicianIds)
            ->with(['employee', 'technicianAvailability', 'visits' => fn ($q) => $q->whereIn('area_id', $areaIds)])
            ->get();

        $data = $technicians->map(fn (User $u) => $this->mapTeamMemberToArray($u, $areaIds, $now, $today))->values()->all();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/supervisor/team/{id} – Single team member detail + their jobs (in_progress, completed).
     * Member must be in supervisor's zones. Jobs include visits in supervisor's scope (areas or assigned by supervisor).
     */
    public function teamMemberShow(Request $request, int $id): JsonResponse
    {
        $areaIds = $this->areaIds($request);
        $technicianIds = $this->teamMemberIdsInZones($areaIds);
        if (! $technicianIds->contains($id)) {
            return response()->json(['success' => false, 'message' => 'Team member not found or not in your zones.'], 404);
        }

        $now = Carbon::now();
        $today = $now->toDateString();
        $u = User::role('technician')
            ->where('id', $id)
            ->with(['employee', 'technicianAvailability'])
            ->firstOrFail();

        $memberVisits = $this->memberVisitsQuery($request, $id)
            ->with(['subscription.client', 'area'])
            ->orderByDesc('scheduled_date')
            ->get();
        $u->setRelation('visits', $memberVisits);

        $data = $this->mapTeamMemberToArray($u, $areaIds, $now, $today, true);
        $data['email'] = $u->email;
        $data['phone'] = $u->phone;
        $data['jobs'] = $this->formatMemberJobsForResponse($memberVisits);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Format member's visits as jobs list: in_progress and completed for supervisor team member detail.
     */
    private function formatMemberJobsForResponse(\Illuminate\Support\Collection $visits): array
    {
        $inProgress = $visits->where('status', 'in_progress')->values();
        $completed = $visits->whereIn('status', ['completed', 'approved'])->values();

        $format = function ($v) {
            $meta = $v->notes ? $this->parseVisitMetaFromNotes((string) $v->notes) : [];
            return [
                'visit_id' => $v->id,
                'status' => $v->status,
                'status_display' => \Illuminate\Support\Str::title(str_replace('_', ' ', $v->status ?? '')),
                'location' => $meta['farm_name'] ?? $v->subscription?->client?->name ?? $v->area?->name ?? null,
                'service' => $meta['service_name'] ?? ($v->subscription?->plan ? str_replace('_', ' ', (string) $v->subscription->plan) : null) ?? 'Visit',
                'scheduled_date' => $v->scheduled_date?->toDateString(),
                'completed_at' => $v->completed_at?->toIso8601String(),
                'duration_minutes' => $meta['duration_minutes'] ?? null,
            ];
        };

        return [
            'in_progress' => $inProgress->map($format)->values()->all(),
            'completed' => $completed->map($format)->values()->all(),
        ];
    }

    /**
     * GET /api/supervisor/team-stats
     * Team Stats: aggregate (visits_today, avg_duration_minutes, customer_rating, open_issues) + members (id, name, initial, completed, rating).
     */
    public function teamStats(Request $request): JsonResponse
    {
        $areaIds = $this->areaIds($request);
        $today = Carbon::today();

        if (empty($areaIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'visits_today' => 0,
                    'avg_duration_minutes' => 0,
                    'customer_rating' => 0,
                    'open_issues' => 0,
                    'members' => [],
                ],
                'message' => 'No zones assigned to you.',
            ]);
        }

        $baseVisits = $this->visitsQuery($request);
        $completedList = (clone $baseVisits)->whereIn('status', ['completed', 'approved'])->get();

        $visitsToday = $completedList->filter(fn ($v) => $v->completed_at && $v->completed_at->isSameDay($today))->count();

        $durations = [];
        foreach ($completedList as $v) {
            if ($v->started_at && $v->completed_at) {
                $durations[] = (int) $v->started_at->diffInMinutes($v->completed_at);
            } else {
                $meta = $this->parseVisitMetaForStats((string) ($v->notes ?? ''));
                if (isset($meta['duration_minutes'])) {
                    $durations[] = (int) $meta['duration_minutes'];
                }
            }
        }
        $avgDurationMinutes = count($durations) > 0 ? (int) round(array_sum($durations) / count($durations)) : 0;

        $ratings = [];
        foreach ($completedList as $v) {
            $meta = $this->parseVisitMetaForStats((string) ($v->notes ?? ''));
            if (isset($meta['rating'])) {
                $ratings[] = (float) $meta['rating'];
            }
        }
        $customerRating = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;

        $openIssues = Complaint::whereHas('visit', function ($q) use ($areaIds) {
            $q->whereIn('area_id', $areaIds);
        })->whereIn('status', ['pending', 'in_progress'])->count();

        $technicianIds = $this->teamMemberIdsInZones($areaIds);
        $members = [];
        if ($technicianIds->isNotEmpty()) {
$technicians = User::role('technician')->whereIn('id', $technicianIds)->get();
            foreach ($technicians as $u) {
                $memberVisits = $this->memberVisitsQuery($request, $u->id)->whereIn('status', ['completed', 'approved'])->get();
                $completed = $memberVisits->count();
                $memberRatings = [];
                foreach ($memberVisits as $v) {
                    $meta = $this->parseVisitMetaForStats((string) ($v->notes ?? ''));
                    if (isset($meta['rating'])) {
                        $memberRatings[] = (float) $meta['rating'];
                    }
                }
                $rating = count($memberRatings) > 0 ? round(array_sum($memberRatings) / count($memberRatings), 1) : 0;
                $initial = mb_substr(trim($u->name), 0, 1) ?: '?';
                $members[] = [
                    'id' => $u->id,
                    'name' => $u->name,
                    'initial' => mb_strtoupper($initial),
                    'completed' => $completed,
                    'rating' => $rating,
                    'account_status' => $u->status ?? 'active',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'visits_today' => $visitsToday,
                'avg_duration_minutes' => $avgDurationMinutes,
                'customer_rating' => $customerRating,
                'open_issues' => $openIssues,
                'members' => $members,
            ],
        ]);
    }

    private function mapTeamMemberToArray(User $u, array $areaIds, Carbon $now, string $today, bool $useAllVisits = false): array
    {
        $visits = $useAllVisits ? $u->visits : $u->visits->whereIn('area_id', $areaIds);
        $totalTasks = $visits->count();
        $completedTasks = $visits->whereIn('status', ['completed', 'approved'])->count();
        $currentVisit = $visits->where('status', 'in_progress')->first();

        $accountStatus = $u->status ?? 'active';
        $onBreak = TechnicianBreak::where('user_id', $u->id)
            ->whereDate('date', $today)
            ->get()
            ->contains(fn ($b) => $this->isTimeInBreak($now, $b->start_time ?? '', $b->end_time ?? ''));

        $status = $accountStatus === 'inactive' ? 'On leave' : ($onBreak ? 'Break' : 'Active');
        $currentActivity = $onBreak ? 'On Break' : ($accountStatus === 'inactive' ? 'On leave' : null);
        if (! $onBreak && $currentVisit) {
            $meta = $currentVisit->notes ? $this->parseVisitMetaFromNotes((string) $currentVisit->notes) : [];
            $loc = $meta['farm_name'] ?? $currentVisit->area?->name ?? $currentVisit->subscription?->client?->name ?? 'Visit';
            $svc = $meta['service_name'] ?? ($currentVisit->subscription?->plan ? str_replace('_', ' ', (string) $currentVisit->subscription->plan) : null) ?? '';
            $currentActivity = $svc ? "{$loc} - {$svc}" : $loc;
        }
        $currentActivity = $currentActivity ?? ($status === 'On leave' ? 'On leave' : ($status === 'Break' ? 'On Break' : '—'));

        return [
            'id' => $u->id,
            'name' => $u->name,
            'employee_id' => $u->employee?->employee_id ?? ('TECH-' . $u->id),
            'profile_picture_url' => $u->profile_picture_url,
            'status' => $status,
            'account_status' => $accountStatus,
            'current_activity' => $currentActivity,
            'tasks_completed' => $completedTasks,
            'tasks_total' => $totalTasks,
            'tasks_display' => $totalTasks > 0 ? "{$completedTasks}/{$totalTasks}" : '0/0',
        ];
    }

    private function isTimeInBreak(Carbon $now, string $startTime, string $endTime): bool
    {
        if ($startTime === '' || $endTime === '') {
            return false;
        }
        $start = Carbon::parse($now->toDateString() . ' ' . $startTime);
        $end = Carbon::parse($now->toDateString() . ' ' . $endTime);
        return $now->between($start, $end);
    }

    /**
     * Pending assignments: unassigned, pending/scheduled, or escalated to supervisor (after 2-3 auto-dispatch failures).
     * Returns paginated data. Message explains when list is empty (no zones vs no assignable visits).
     */
    public function assignmentsPending(Request $request): JsonResponse
    {
        $areaIds = $this->areaIds($request);
        if (empty($areaIds)) {
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator([], 0, (int) $request->get('per_page', 20));
            $paginator->setPath($request->url());
            return response()->json([
                'success' => true,
                'data' => $paginator,
                'message' => 'No zones assigned to you. Ask admin to assign you to areas (Admin Areas) so you can see and assign visits.',
            ]);
        }

        $pending = $this->assignableVisitsQuery($request)
            ->with('supervisor')
            ->orderByRaw('escalated_at IS NOT NULL DESC')
            ->orderBy('scheduled_date')
            ->paginate((int) $request->get('per_page', 20));

        $pending->getCollection()->transform(function ($visit) {
            $visit->makeHidden(['subscription_id', 'area_id', 'subscription', 'area']);
            $meta = $this->parseVisitMetaFromNotes((string) ($visit->notes ?? ''));
            $visit->title = $meta['farm_name'] ?? ('Task #' . $visit->id);
            $visit->service_name = $meta['service_name'] ?? null;
            $visit->location = $meta['location'] ?? null;
            $visit->duration_minutes = $meta['duration_minutes'] ?? null;
            $visit->price_display = $visit->price !== null ? 'AED ' . number_format((float) $visit->price, 2) : ($meta['price_display'] ?? null);
            $visit->supervisor_name = $visit->supervisor?->name ?? null;
            $visit->address = $visit->location;
            $visit->job_time = $visit->scheduled_date ? Carbon::parse($visit->scheduled_date)->format('d M Y, h:i A') : null;
            $visit->makeHidden(['supervisor']);
            return $visit;
        });

        $message = null;
        if ($pending->isEmpty()) {
            $message = 'No assignable visits in your zones. Visits must have area_id set to one of your zones to appear here. Create visits with an area in your zone, or wait for new jobs.';
        }

        return response()->json(array_filter([
            'success' => true,
            'data' => $pending,
            'message' => $message,
        ]));
    }

    /**
     * GET /api/supervisor/assignments/{id}
     * Single assignment detail (same shape as one item from the list) + subscription + customer who ordered the service.
     */
    public function assignmentsShow(Request $request, int $id): JsonResponse
    {
        $visit = $this->editableAssignmentVisitsQuery($request)
            ->with('supervisor', 'technician', 'subscription.client')
            ->find($id);
        if (! $visit) {
            return response()->json(['success' => false, 'message' => 'Assignment not found.'], 404);
        }
        $sub = $visit->subscription;
        $client = $sub?->client;
        $visit->makeHidden(['subscription_id', 'area_id', 'subscription', 'area']);
        $meta = $this->parseVisitMetaFromNotes((string) ($visit->notes ?? ''));
        $visit->title = $meta['farm_name'] ?? ('Task #' . $visit->id);
        $visit->service_name = $meta['service_name'] ?? null;
        $visit->location = $meta['location'] ?? null;
        $visit->duration_minutes = $meta['duration_minutes'] ?? null;
        $visit->price_display = $visit->price !== null ? 'AED ' . number_format((float) $visit->price, 2) : ($meta['price_display'] ?? null);
        $visit->supervisor_name = $visit->supervisor?->name ?? null;
        $visit->address = $visit->location;
        $visit->job_time = $visit->scheduled_date ? Carbon::parse($visit->scheduled_date)->format('d M Y, h:i A') : null;
        $visit->makeHidden(['supervisor']);
        $visit->customer = $client ? [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone ?? null,
            'profile_picture_url' => $client->profile_picture_url ?? null,
        ] : null;
        $visit->subscription = $sub ? [
            'id' => $sub->id,
            'plan' => $sub->plan,
            'start_date' => $sub->start_date?->format('Y-m-d'),
            'end_date' => $sub->end_date?->format('Y-m-d'),
            'amount' => $sub->amount !== null ? (float) $sub->amount : null,
            'payment_status' => $sub->payment_status ?? null,
            'total_visits' => $sub->total_visits ?? null,
            'completed_visits' => $sub->completed_visits ?? null,
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone ?? null,
                'profile_picture_url' => $client->profile_picture_url ?? null,
            ] : null,
        ] : null;

        return response()->json([
            'success' => true,
            'data' => $visit,
        ]);
    }

    /**
     * GET /api/supervisor/assign-tasks
     * Assign Tasks screen: team_members (same as GET /team) + available_tasks (same as GET /assignments first page).
     */
    public function assignTasksPage(Request $request): JsonResponse
    {
        $teamResponse = $this->myTeam($request);
        $teamData = $teamResponse->getData(true);
        $team_members = $teamData['data'] ?? [];

        $pending = $this->assignableVisitsQuery($request)
                ->with('supervisor')
                ->orderByRaw('escalated_at IS NOT NULL DESC')
                ->orderBy('scheduled_date')
                ->paginate((int) $request->get('per_page', 50));
            $pending->getCollection()->transform(function ($visit) {
                $visit->makeHidden(['subscription_id', 'area_id', 'subscription', 'area']);
                $meta = $this->parseVisitMetaFromNotes((string) ($visit->notes ?? ''));
                $visit->title = $meta['farm_name'] ?? ('Task #' . $visit->id);
                $visit->service_name = $meta['service_name'] ?? null;
                $visit->location = $meta['location'] ?? null;
                $visit->duration_minutes = $meta['duration_minutes'] ?? null;
                $visit->price_display = $visit->price !== null ? 'AED ' . number_format((float) $visit->price, 2) : ($meta['price_display'] ?? null);
                $visit->supervisor_name = $visit->supervisor?->name ?? null;
                $visit->address = $visit->location;
                $visit->job_time = $visit->scheduled_date ? Carbon::parse($visit->scheduled_date)->format('d M Y, h:i A') : null;
                $visit->makeHidden(['supervisor']);
                return $visit;
            });
        $available_tasks = $pending->getCollection()->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'team_members' => $team_members,
                'available_tasks' => $available_tasks,
            ],
        ]);
    }

    /**
     * POST /api/supervisor/assignments/{id}
     * Simple URL: id = task/visit id. Assign task to technician or update assignment.
     * Body: technician_id (required to assign), scheduled_date (optional). No visit_id or notes in body.
     * Example: POST /api/supervisor/assignments/44 with {"technician_id": 3}
     */
    public function assignmentsAssignOrUpdate(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => 'nullable|integer|exists:users,id',
            'scheduled_date' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $visit = $this->editableAssignmentVisitsQuery($request)->findOrFail($id);

        if ($request->filled('technician_id')) {
            if ($visit->status === 'pending_acceptance' && $visit->accept_by && Carbon::parse($visit->accept_by)->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This job is already offered to a technician. You can assign it to someone else only after they reject it or the acceptance time (accept_by) expires.',
                ], 422);
            }
            $technician = User::role('technician')->active()->find((int) $request->input('technician_id'));
            if (! $technician) {
                return response()->json(['success' => false, 'message' => 'Technician not found or inactive. Only active technicians can be assigned.'], 404);
            }
            $areaIds = $this->areaIds($request);
            if (! empty($areaIds) && ! $technician->assignedAreas()->whereIn('areas.id', $areaIds)->exists()) {
                return response()->json(['success' => false, 'message' => 'Technician is not in your assigned zones. Choose a team member from your zones.'], 422);
            }
            $visit->supervisor_id = $request->user()->id;
            $visit->escalated_at = null;
            $visit->offer_count = 0;
            $visit->technician_id = $technician->id;
            if (! $visit->area_id && ! empty($areaIds)) {
                $techAreaId = $technician->assignedAreas()->whereIn('areas.id', $areaIds)->value('areas.id');
                if ($techAreaId) {
                    $visit->area_id = $techAreaId;
                } else {
                    $visit->area_id = $areaIds[0];
                }
            }
            if ($request->filled('scheduled_date')) {
                $visit->scheduled_date = $request->input('scheduled_date');
            }
            $visit->save();
            VisitOfferService::offerToTechnician($visit, $technician->id);
            $visit->load(['technician']);

            return response()->json([
                'success' => true,
                'message' => 'Job offered to technician. They have ' . VisitOfferService::ACCEPT_MINUTES . ' minutes to accept.',
                'data' => $visit->makeHidden(['subscription_id', 'area_id', 'subscription', 'area']),
                'accept_by' => $visit->accept_by?->toIso8601String(),
            ], 200);
        }

        if ($request->filled('scheduled_date')) {
            $visit->scheduled_date = $request->input('scheduled_date');
            $visit->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated.',
            'data' => $visit->makeHidden(['subscription_id', 'area_id', 'subscription', 'area']),
        ]);
    }

    public function assignmentsStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'visit_id' => 'required|integer|exists:visits,id',
            'technician_id' => 'required|integer|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $visit = $this->assignableVisitsQuery($request)->findOrFail((int) $request->input('visit_id'));
        if ($visit->status === 'pending_acceptance' && $visit->accept_by && Carbon::parse($visit->accept_by)->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'This job is already offered to a technician. You can assign it to someone else only after they reject it or the acceptance time (accept_by) expires.',
            ], 422);
        }
        $technician = User::role('technician')->active()->find((int) $request->input('technician_id'));
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'Technician not found or inactive. Only active technicians can be assigned.'], 404);
        }
        $areaIds = $this->areaIds($request);
        if (! empty($areaIds) && ! $technician->assignedAreas()->whereIn('areas.id', $areaIds)->exists()) {
            return response()->json(['success' => false, 'message' => 'Technician is not in your assigned zones. Choose a team member from your zones.'], 422);
        }

        $visit->supervisor_id = $request->user()->id;
        $visit->escalated_at = null;
        $visit->offer_count = 0;
        $visit->technician_id = $technician->id;
        if (! $visit->area_id && ! empty($areaIds)) {
            $techAreaId = $technician->assignedAreas()->whereIn('areas.id', $areaIds)->value('areas.id');
            $visit->area_id = $techAreaId ?: $areaIds[0];
        }
        if ($request->filled('scheduled_date')) {
            $visit->scheduled_date = $request->input('scheduled_date');
        }
        if ($request->filled('note')) {
            $visit->notes = trim(($visit->notes ? $visit->notes . PHP_EOL : '') . $request->input('note'));
        }
        $visit->save();
        VisitOfferService::offerToTechnician($visit, $technician->id);
        $visit->load(['technician']);

        return response()->json([
            'success' => true,
            'message' => 'Job offered to technician. They have ' . VisitOfferService::ACCEPT_MINUTES . ' minutes to accept.',
            'data' => $visit->makeHidden(['subscription_id', 'area_id', 'subscription', 'area']),
            'accept_by' => $visit->accept_by?->toIso8601String(),
        ], 201);
    }

    public function assignmentsUpdate(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => 'nullable|integer|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $visit = $this->editableAssignmentVisitsQuery($request)->findOrFail($id);

        if ($request->filled('technician_id')) {
            $technician = User::role('technician')->active()->find((int) $request->input('technician_id'));
            if (! $technician) {
                return response()->json(['success' => false, 'message' => 'Technician not found or inactive. Only active technicians can be assigned.'], 404);
            }
            $areaIds = $this->areaIds($request);
            if (! empty($areaIds) && ! $technician->assignedAreas()->whereIn('areas.id', $areaIds)->exists()) {
                return response()->json(['success' => false, 'message' => 'Technician is not in your assigned zones. Choose a team member from your zones.'], 422);
            }
            $visit->technician_id = $technician->id;
            $visit->supervisor_id = $request->user()->id;
            $visit->escalated_at = null;
            if (! $visit->area_id && ! empty($areaIds)) {
                $techAreaId = $technician->assignedAreas()->whereIn('areas.id', $areaIds)->value('areas.id');
                $visit->area_id = $techAreaId ?: $areaIds[0];
            }
        }
        if ($request->filled('scheduled_date')) {
            $visit->scheduled_date = $request->input('scheduled_date');
        }
        if ($request->filled('note')) {
            $visit->notes = trim(($visit->notes ? $visit->notes . PHP_EOL : '') . $request->input('note'));
        }
        $visit->save();

        return response()->json(['success' => true, 'message' => 'Assignment updated successfully.', 'data' => $visit]);
    }

    public function assignmentsReassign(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $visit = $this->editableAssignmentVisitsQuery($request)->findOrFail($id);
        if ($visit->status === 'pending_acceptance' && $visit->accept_by && Carbon::parse($visit->accept_by)->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'This job is already offered to a technician. You can reassign only after they reject it or the acceptance time (accept_by) expires.',
            ], 422);
        }
        $technician = User::role('technician')->active()->find((int) $request->input('technician_id'));
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'Technician not found or inactive. Only active technicians can be assigned.'], 404);
        }
        $areaIds = $this->areaIds($request);
        if (! empty($areaIds) && ! $technician->assignedAreas()->whereIn('areas.id', $areaIds)->exists()) {
            return response()->json(['success' => false, 'message' => 'Technician is not in your assigned zones. Choose a team member from your zones.'], 422);
        }

        $visit->technician_id = $technician->id;
        $visit->supervisor_id = $request->user()->id;
        $visit->escalated_at = null;
        if (! $visit->area_id && ! empty($areaIds)) {
            $techAreaId = $technician->assignedAreas()->whereIn('areas.id', $areaIds)->value('areas.id');
            $visit->area_id = $techAreaId ?: $areaIds[0];
        }
        if ($request->filled('reason')) {
            $visit->notes = trim(($visit->notes ? $visit->notes . PHP_EOL : '') . 'Reassign reason: ' . $request->input('reason'));
        }
        $visit->save();

        return response()->json(['success' => true, 'message' => 'Assignment reassigned successfully.', 'data' => $visit]);
    }

    /**
     * List field reports (reports given by technician to supervisor). Optional: ?status=pending&per_page=20.
     * Shows reports for: (1) visits that are in_progress, or (2) reports still pending (so supervisor can accept/reject even after job is completed).
     * Includes visits in supervisor's zones OR visits assigned to this supervisor (supervisor_id = me), so reports show even when visit.area_id is null.
     * Returns only what the UI needs: technician name, employee_id, location, service, submitted_at, before_photos, after_photos, etc.
     */
    public function reportsIndex(Request $request): JsonResponse
    {
        $query = $this->reportsForSupervisorQuery($request)
            ->whereNot('status', 'sent_to_client') // exclude reports already sent to client
            ->where(function ($q) {
                $q->whereHas('visit', fn ($v) => $v->where('status', 'in_progress'))
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'pending')
                            ->whereHas('visit', fn ($v) => $v->whereIn('status', ['completed', 'approved']));
                    });
            })
            ->with([
                'visit.subscription.client',
                'visit.technician.employee',
                'visit.area',
                'visit.photos',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reports = $query->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 20));

        $message = $reports->isEmpty()
            ? 'No reports yet. Technicians submit field reports for jobs assigned to you; they appear here when sent (report is linked to you by supervisor_id).'
            : null;

        $data = $reports->getCollection()->map(function (Report $report) {
            $visit = $report->visit;
            $technician = $visit?->technician;
            $client = $visit?->subscription?->client;
            $meta = $visit && $visit->notes
                ? $this->parseVisitMetaFromNotes((string) $visit->notes)
                : [];

            $photos = $visit?->photos ?? collect();
            $beforePhotos = $photos->where('type', 'before')->values()->map(fn ($p) => [
                'id' => $p->id,
                'photo_url' => ProfilePictureUploadService::fullUrl($p->photo_path),
                'type' => 'before',
            ])->values()->all();
            $afterPhotos = $photos->where('type', 'after')->values()->map(fn ($p) => [
                'id' => $p->id,
                'photo_url' => ProfilePictureUploadService::fullUrl($p->photo_path),
                'type' => 'after',
            ])->values()->all();

            return [
                'id' => $report->id,
                'visit_id' => $report->visit_id,
                'status' => $report->status,
                'technician_name' => $technician?->name,
                'employee_id' => $technician?->employee?->employee_id ?? ($technician ? 'TECH-' . $technician->id : null),
                'location' => $meta['farm_name'] ?? $client?->name ?? $visit?->area?->name ?? null,
                'service' => $meta['service_name'] ?? ($visit?->subscription?->plan ? str_replace('_', ' ', (string) $visit->subscription->plan) : null) ?? 'Visit',
                'submitted_at' => $report->created_at?->toIso8601String(),
                'has_photos' => $photos->count() > 0,
                'before_photos' => $beforePhotos,
                'after_photos' => $afterPhotos,
                'technician_notes' => $report->technician_notes,
            ];
        })->values();

        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
            'links' => [
                'first' => $reports->url(1),
                'last' => $reports->url($reports->lastPage()),
                'prev' => $reports->previousPageUrl(),
                'next' => $reports->nextPageUrl(),
            ],
        ];
        if ($message !== null) {
            $payload['message'] = $message;
        }
        return response()->json($payload);
    }

    private function parseVisitMetaFromNotes(string $notes): array
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));
        $farm = $parts[0] ?? null;
        $service = isset($parts[1]) ? trim($parts[1]) : null;
        if ($service && preg_match('/^(.+?)\s+Visit\s*$/i', $service, $m)) {
            $service = trim($m[1]);
        }
        $location = isset($parts[2]) ? trim($parts[2]) : null;
        $duration_minutes = null;
        if (isset($parts[3]) && preg_match('/(\d+)\s*min/i', $parts[3], $m)) {
            $duration_minutes = (int) $m[1];
        }
        $price_display = isset($parts[4]) ? trim($parts[4]) : null;
        return [
            'farm_name' => $farm ?: null,
            'service_name' => $service ?: null,
            'location' => $location ?: null,
            'duration_minutes' => $duration_minutes,
            'price_display' => $price_display ?: null,
        ];
    }

    /** Parse duration and rating from visit notes (e.g. seeded format with "120 min" and "4.6/5"). */
    private function parseVisitMetaForStats(string $notes): array
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? '');
        if ($clean === '') {
            return [];
        }
        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));
        $meta = [];
        if (isset($parts[3]) && preg_match('/(\d+)\s*min/i', $parts[3], $m)) {
            $meta['duration_minutes'] = (int) $m[1];
        }
        if (isset($parts[5]) && preg_match('/([0-9]+(?:\.[0-9]+)?)\s*\/\s*5/', $parts[5], $m)) {
            $meta['rating'] = (float) $m[1];
        } elseif (isset($parts[3]) && preg_match('/([0-9]+(?:\.[0-9]+)?)\s*\/\s*5/', $parts[3], $m)) {
            $meta['rating'] = (float) $m[1];
        }
        return $meta;
    }

    /**
     * POST /api/supervisor/reports/{id}/accept
     * Supervisor accepts the field report. After this, the technician can set the job status to completed.
     */
    public function reportAccept(Request $request, int $id): JsonResponse
    {
        $report = $this->reportsForSupervisorQuery($request)->where('id', $id)->first();

        if (! $report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }
        if ($report->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Report is not pending.'], 422);
        }

        $report->status = 'approved';
        $report->approved_by = $request->user()->id;
        $report->approved_at = now();
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Report accepted. Technician can now complete the job.',
            'data' => $report->load(['visit', 'visit.technician']),
        ]);
    }

    /**
     * POST /api/supervisor/reports/{id}/reject
     * Supervisor rejects the field report. Technician cannot complete until they resubmit and it is accepted.
     */
    public function reportReject(Request $request, int $id): JsonResponse
    {
        $report = $this->reportsForSupervisorQuery($request)->where('id', $id)->first();

        if (! $report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }
        if ($report->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Report is not pending.'], 422);
        }

        $report->status = 'rejected';
        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Report rejected.',
            'data' => $report->load(['visit', 'visit.technician']),
        ]);
    }

    public function reportsGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'format' => 'nullable|string|in:pdf,csv,xlsx',
        ]);

        $report = AdminReport::create([
            'title' => $validated['title'] ?? 'Supervisor Report',
            'type' => $validated['type'] ?? 'operational',
            'status' => 'generated',
            'generated_at' => now(),
            'format' => $validated['format'] ?? 'csv',
            'parameters' => [
                'scope' => 'supervisor',
                'area_ids' => $this->areaIds($request),
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Report generated successfully.', 'data' => $report], 201);
    }

    /**
     * GET /api/supervisor/reports/{id}
     * Show a single field report (from technician). Id is the Report id from the list (GET /reports).
     */
    public function reportsShow(Request $request, int $id): JsonResponse
    {
        $report = $this->reportsForSupervisorQuery($request)
            ->where('id', $id)
            ->with([
                'visit.subscription.client',
                'visit.technician.employee',
                'visit.area',
                'visit.photos',
            ])
            ->first();

        if (! $report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        $visit = $report->visit;
        $technician = $visit?->technician;
        $client = $visit?->subscription?->client;
        $meta = $visit && $visit->notes
            ? $this->parseVisitMetaFromNotes((string) $visit->notes)
            : [];
        $photos = $visit?->photos ?? collect();
        $beforePhotos = $photos->where('type', 'before')->values()->map(fn ($p) => [
            'id' => $p->id,
            'photo_url' => ProfilePictureUploadService::fullUrl($p->photo_path),
            'type' => 'before',
        ])->values()->all();
        $afterPhotos = $photos->where('type', 'after')->values()->map(fn ($p) => [
            'id' => $p->id,
            'photo_url' => ProfilePictureUploadService::fullUrl($p->photo_path),
            'type' => 'after',
        ])->values()->all();

        $data = [
            'id' => $report->id,
            'visit_id' => $report->visit_id,
            'supervisor_id' => $report->supervisor_id,
            'status' => $report->status,
            'technician_name' => $technician?->name,
            'employee_id' => $technician?->employee?->employee_id ?? ($technician ? 'TECH-' . $technician->id : null),
            'location' => $meta['farm_name'] ?? $client?->name ?? $visit?->area?->name ?? null,
            'service' => $meta['service_name'] ?? ($visit?->subscription?->plan ? str_replace('_', ' ', (string) $visit->subscription->plan) : null) ?? 'Visit',
            'submitted_at' => $report->created_at?->toIso8601String(),
            'technician_notes' => $report->technician_notes,
            'before_photos' => $beforePhotos,
            'after_photos' => $afterPhotos,
            'visit' => $visit ? [
                'id' => $visit->id,
                'status' => $visit->status,
                'status_display' => \Illuminate\Support\Str::title(str_replace('_', ' ', $visit->status ?? '')),
                'scheduled_at' => $visit->scheduled_at?->toIso8601String(),
                'client_name' => $client?->name,
                'area_name' => $visit->area?->name,
            ] : null,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function reportsDownload(Request $request, int $id): StreamedResponse
    {
        $report = AdminReport::where('id', $id)
            ->where('created_by', $request->user()->id)
            ->firstOrFail();

        $filename = 'supervisor_report_' . $report->id . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['report_id', 'title', 'type', 'status', 'generated_at']);
            fputcsv($out, [$report->id, $report->title, $report->type, $report->status, $report->generated_at]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * GET /api/supervisor/areas – List all zones (areas) for supervisor to set as service areas in profile.
     * Areas = cities (e.g. UAE). Supervisor picks from this list when updating profile (area_ids).
     */
    public function areasList(Request $request): JsonResponse
    {
        $areas = Area::orderBy('name')->get(['id', 'name', 'description', 'country']);
        $data = $areas->map(fn (Area $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'description' => $a->description,
            'country' => $a->country ?? 'UAE',
        ])->values()->all();
        return response()->json([
            'success' => true,
            'message' => 'Areas (zones) for service area selection. Use these ids in profile update (area_ids).',
            'data' => $data,
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('supervisedAreas');

        $visitsQuery = $this->visitsQuery($request);
        $completedVisits = (clone $visitsQuery)->whereIn('status', ['completed', 'approved']);
        $jobsCompleted = $completedVisits->count();
        $totalEarnings = (clone $completedVisits)->get()->sum(fn ($v) => (float) ($v->price ?? 0));

        $serviceAreas = $user->supervisedAreas->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'country' => $a->country ?? 'UAE',
        ])->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
                'role' => $user->role,
                'service_areas' => $serviceAreas,
                'jobs_completed' => $jobsCompleted,
                'total_earnings' => round($totalEarnings, 2),
                'total_earnings_display' => 'AED ' . number_format($totalEarnings, 2),
                'member_since' => $user->created_at?->toDateString(),
                'rating' => 0,
                'rating_jobs' => 0,
            ],
        ]);
    }

    /**
     * Single profile update API (form-data).
     * Accepts: name, email, phone, profile_picture (file). All fields optional. No password fields.
     * Areas (zones) are assigned by admin only; supervisor cannot set area_ids via profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $profileFile = $request->file('profile_picture');
        if (is_array($profileFile)) {
            $profileFile = $profileFile[0] ?? null;
        }

        $input = $request->all();
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
        ];
        if ($profileFile) {
            $rules['profile_picture'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp';
        }
        $validator = Validator::make(array_merge($input, ['profile_picture' => $profileFile]), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->input('name');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email');
        }
        if ($request->has('phone')) {
            $user->phone = $request->input('phone') ?: null;
        }

        // Profile picture: POST has $request->file(); PUT + multipart must be parsed from raw body
        if ($profileFile && is_object($profileFile) && method_exists($profileFile, 'store')) {
            $stored = $profileFile->store('profiles', 'public');
            $user->profile_picture = $stored;
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        } elseif ($request->isMethod('PUT') && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $stored = ProfilePictureUploadService::storeFromMultipartPut($request);
            if ($stored) {
                $user->profile_picture = $stored;
                ImageCompressionService::compressIfNeededFromPublicPath($stored);
            }
        }

        $user->save();

        $user->load('supervisedAreas');
        $serviceAreas = $user->supervisedAreas->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'country' => $a->country ?? 'UAE',
        ])->values()->all();

        return response()->json(['success' => true, 'message' => 'Profile updated successfully.', 'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture' => $user->profile_picture,
            'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
            'service_areas' => $serviceAreas,
        ]]);
    }

    public function profilePreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'language' => 'en',
                'timezone' => config('app.timezone', 'UTC'),
                'notifications' => [
                    'push_enabled' => true,
                    'email_enabled' => true,
                ],
            ],
        ]);
    }
}

