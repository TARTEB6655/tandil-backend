<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminReport;
use App\Models\Report;
use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        return Visit::query()->whereIn('area_id', $this->areaIds($request));
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

        $teamMembersCount = User::role('technician')
            ->whereHas('visits', fn ($q) => $q->whereIn('area_id', $areaIds))
            ->count();

        $activeVisitsCount = (clone $query)
            ->whereIn('status', ['pending', 'scheduled', 'in_progress'])
            ->count();

        $completedVisitsCount = (clone $query)
            ->whereIn('status', ['completed', 'approved'])
            ->count();

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

    public function dashboardTeamStatus(Request $request): JsonResponse
    {
        $areaIds = $this->areaIds($request);

        $technicians = User::role('technician')
            ->whereHas('visits', fn ($q) => $q->whereIn('area_id', $areaIds))
            ->with('technicianAvailability')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_online' => (bool) ($u->technicianAvailability?->is_online ?? false),
                'profile_picture_url' => $u->profile_picture_url,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $technicians]);
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

    public function teamStatistics(Request $request): JsonResponse
    {
        $areaIds = $this->areaIds($request);
        $visits = $this->visitsQuery($request)->get(['technician_id', 'status']);

        $byTechnician = $visits
            ->filter(fn ($v) => ! empty($v->technician_id))
            ->groupBy('technician_id')
            ->map(fn ($items, $technicianId) => [
                'technician_id' => (int) $technicianId,
                'assigned' => $items->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'in_progress' => $items->where('status', 'in_progress')->count(),
                'pending' => $items->whereIn('status', ['pending', 'scheduled'])->count(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'areas' => $areaIds,
                'technician_stats' => $byTechnician,
            ],
        ]);
    }

    public function teamPerformance(Request $request): JsonResponse
    {
        $data = $this->teamStatistics($request)->getData(true);
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_technicians' => count($data['data']['technician_stats']),
                    'generated_at' => now()->toIso8601String(),
                ],
                'technician_performance' => $data['data']['technician_stats'],
            ],
        ]);
    }

    public function teamAttendance(Request $request): JsonResponse
    {
        $areaIds = $this->areaIds($request);
        $technicians = User::role('technician')
            ->whereHas('visits', fn ($q) => $q->whereIn('area_id', $areaIds))
            ->with('technicianAvailability')
            ->get()
            ->map(fn (User $u) => [
                'technician_id' => $u->id,
                'name' => $u->name,
                'is_online' => (bool) ($u->technicianAvailability?->is_online ?? false),
                'working_days' => $u->technicianAvailability?->working_days ?? [],
            ])->values();

        return response()->json(['success' => true, 'data' => $technicians]);
    }

    public function teamWorkload(Request $request): JsonResponse
    {
        $start = Carbon::today();
        $end = Carbon::today()->addDays(7);

        $workload = $this->visitsQuery($request)
            ->whereBetween('scheduled_date', [$start, $end])
            ->whereNotNull('technician_id')
            ->selectRaw('technician_id, count(*) as assigned_count')
            ->groupBy('technician_id')
            ->get();

        return response()->json(['success' => true, 'data' => $workload]);
    }

    public function assignmentsPending(Request $request): JsonResponse
    {
        $pending = $this->visitsQuery($request)
            ->where(function ($q) {
                $q->whereNull('technician_id')
                    ->orWhereIn('status', ['pending', 'scheduled']);
            })
            ->with(['subscription.client', 'area'])
            ->orderBy('scheduled_date')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $pending]);
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

        $visit = $this->visitsQuery($request)->findOrFail((int) $request->input('visit_id'));
        $technician = User::role('technician')->find((int) $request->input('technician_id'));
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'Technician not found.'], 404);
        }

        $visit->technician_id = $technician->id;
        $visit->supervisor_id = $request->user()->id;
        if ($request->filled('scheduled_date')) {
            $visit->scheduled_date = $request->input('scheduled_date');
        }
        if ($request->filled('note')) {
            $visit->notes = trim(($visit->notes ? $visit->notes . PHP_EOL : '') . $request->input('note'));
        }
        $visit->save();

        return response()->json(['success' => true, 'message' => 'Technician assigned successfully.', 'data' => $visit], 201);
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

        $visit = $this->visitsQuery($request)->findOrFail($id);

        if ($request->filled('technician_id')) {
            $technician = User::role('technician')->find((int) $request->input('technician_id'));
            if (! $technician) {
                return response()->json(['success' => false, 'message' => 'Technician not found.'], 404);
            }
            $visit->technician_id = $technician->id;
            $visit->supervisor_id = $request->user()->id;
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

        $visit = $this->visitsQuery($request)->findOrFail($id);
        $technician = User::role('technician')->find((int) $request->input('technician_id'));
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'Technician not found.'], 404);
        }

        $visit->technician_id = $technician->id;
        $visit->supervisor_id = $request->user()->id;
        if ($request->filled('reason')) {
            $visit->notes = trim(($visit->notes ? $visit->notes . PHP_EOL : '') . 'Reassign reason: ' . $request->input('reason'));
        }
        $visit->save();

        return response()->json(['success' => true, 'message' => 'Assignment reassigned successfully.', 'data' => $visit]);
    }

    /**
     * List field reports (reports given by technician to supervisor). Optional: ?status=pending&per_page=20.
     * Only reports for visits that are in_progress are shown (reports for jobs still in progress, not completed/approved).
     * Returns only what the UI needs: technician name, employee_id, location, service, submitted_at, before_photos, after_photos, etc.
     */
    public function reportsIndex(Request $request): JsonResponse
    {
        $visitIds = $this->visitsQuery($request)->pluck('id');
        $query = Report::whereIn('visit_id', $visitIds)
            ->whereHas('visit', fn ($q) => $q->where('status', 'in_progress'))
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

        return response()->json([
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
        ]);
    }

    private function parseVisitMetaFromNotes(string $notes): array
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        $parts = array_map('trim', explode('|', $clean));
        $farm = $parts[0] ?? null;
        $service = isset($parts[1]) ? trim($parts[1]) : null;
        if ($service && preg_match('/^(.+?)\s+Visit\s*$/i', $service, $m)) {
            $service = trim($m[1]);
        }
        return [
            'farm_name' => $farm ?: null,
            'service_name' => $service ?: null,
        ];
    }

    /**
     * POST /api/supervisor/reports/{id}/accept
     * Supervisor accepts the field report. After this, the technician can set the job status to completed.
     */
    public function reportAccept(Request $request, int $id): JsonResponse
    {
        $visitIds = $this->visitsQuery($request)->pluck('id');
        $report = Report::whereIn('visit_id', $visitIds)->where('id', $id)->first();

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
        $visitIds = $this->visitsQuery($request)->pluck('id');
        $report = Report::whereIn('visit_id', $visitIds)->where('id', $id)->first();

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

    public function reportsShow(Request $request, int $id): JsonResponse
    {
        $report = AdminReport::where('id', $id)
            ->where('created_by', $request->user()->id)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $report]);
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

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        $visitsQuery = $this->visitsQuery($request);
        $completedVisits = (clone $visitsQuery)->whereIn('status', ['completed', 'approved']);
        $jobsCompleted = $completedVisits->count();
        $totalEarnings = (clone $completedVisits)->get()->sum(fn ($v) => (float) ($v->price ?? 0));

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
     * Accepts: name, email, phone, profile_picture (file), password, password_confirmation.
     * All fields optional. To change password send password + password_confirmation (no current_password).
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
            'password' => 'nullable|string|min:8|confirmed',
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

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
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

        return response()->json(['success' => true, 'message' => 'Profile updated successfully.', 'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture' => $user->profile_picture,
            'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
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

