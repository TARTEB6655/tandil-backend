<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use App\Models\TechnicianAvailability;
use App\Models\TechnicianBankAccount;
use App\Models\TechnicianBreak;
use App\Models\TechnicianVacation;
use App\Models\Visit;
use App\Models\Report;
use App\Models\VisitPhoto;
use App\Models\Tip;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TechnicianDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/technician/dashboard
     * Home: name, employee ID, online status, weekly KPIs, today's tasks (visits). Greeting handled on frontend.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;
        $availability = $user->technicianAvailability;

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $visitsThisWeek = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$weekStart, $weekEnd])
            ->where('status', 'completed')
            ->count();
        $todayVisits = Visit::where('technician_id', $user->id)
            ->whereDate('scheduled_date', '<=', Carbon::today())
            ->whereIn('status', $this->openJobStatuses())
            ->orderBy('scheduled_date')
            ->with(['subscription.client', 'area'])
            ->get();
        $recentCompletedVisits = Visit::where('technician_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->with(['subscription.client', 'area'])
            ->take(5)
            ->get();

        // Stub earnings/rating until payment module exists
        $weeklyEarnings = 0;
        $avgRating = 0;

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture) ?? $user->profile_picture_url,
                'employee_id' => $employee?->employee_id ?? ('TECH-' . $user->id),
                'designation' => $employee?->designation ?? 'Field Worker',
                'is_online' => $availability?->is_online ?? true,
                'weekly_kpis' => [
                    'earnings' => $weeklyEarnings,
                    'visits_done' => $visitsThisWeek,
                    'rating' => $avgRating,
                ],
                'today_tasks' => $todayVisits->map(fn ($v) => $this->formatVisitAsTask($v)),
                'recent_visits' => $recentCompletedVisits->map(fn ($v) => $this->formatVisitAsRecentVisit($v)),
            ],
        ]);
    }

    /**
     * POST /api/technician/tasks/{id}/accept - Accept assigned task (visit).
     */
    public function taskAccept(Request $request, $id)
    {
        $visit = Visit::where('technician_id', $request->user()->id)->find($id);
        if (!$visit) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }
        if ($visit->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Task cannot be accepted in current status.'], 422);
        }
        $visit->status = 'accepted';
        $visit->accepted_at = now();
        $visit->save();
        return response()->json(['success' => true, 'data' => $this->formatVisitAsTask($visit->load('subscription.client', 'area'))]);
    }

    /**
     * POST /api/technician/tasks/{id}/reject - Reject task (optional reason).
     */
    public function taskReject(Request $request, $id)
    {
        $visit = Visit::where('technician_id', $request->user()->id)->find($id);
        if (!$visit) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }
        if ($visit->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Task cannot be rejected in current status.'], 422);
        }
        $reason = $request->input('reason');
        $visit->status = 'rejected';
        $visit->notes = $visit->notes ? $visit->notes . "\nRejected: " . ($reason ?? '') : ($reason ?? 'Rejected by technician');
        $visit->save();
        return response()->json(['success' => true, 'data' => ['id' => $visit->id, 'status' => 'rejected']]);
    }

    /**
     * GET /api/technician/profile - Full profile (personal info, notification prefs). Service areas are in GET /api/technician/availability.
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load('employee', 'technicianAvailability');
        $employee = $user->employee;
        $visitsCompleted = Visit::where('technician_id', $user->id)->where('status', 'completed')->count();

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture' => $user->profile_picture,
            'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture) ?? $user->profile_picture_url,
            'employee_id' => $employee?->employee_id ?? ('TECH-' . $user->id),
            'rating' => 0,
            'jobs_completed' => $visitsCompleted,
            'total_earnings' => 0,
            'member_since' => $user->created_at?->toIso8601String(),
            'notification_preferences' => [
                'push_enabled' => true,
                'email_enabled' => true,
            ],
            'availability' => $user->technicianAvailability ? [
                'is_online' => $user->technicianAvailability->is_online,
                'auto_accept_jobs' => $user->technicianAvailability->auto_accept_jobs,
                'working_days' => $user->technicianAvailability->working_days ?? [],
                'working_hours_slots' => $user->technicianAvailability->working_hours_slots ?? [],
            ] : null,
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PUT /api/technician/profile - Update technician profile.
     * Accepts multipart/form-data: name, email, phone, current_password, password, password_confirmation, profile_picture (file). All optional.
     * For service areas use GET/PUT /api/technician/service-areas. For specializations use GET/PUT /api/technician/specializations.
     * Returns full profile (same shape as GET /api/technician/profile).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user()->load('employee', 'technicianAvailability');

        // Resolve single file (Postman may send multiple files as array). PUT + multipart: use service.
        $profileFile = $request->file('profile_picture');
        if (is_array($profileFile)) {
            $profileFile = $profileFile[0] ?? null;
        }
        $storedFromPut = null;
        if (! $profileFile && $request->isMethod('PUT') && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $storedFromPut = ProfilePictureUploadService::storeFromMultipartPut($request);
        }
        $input = $request->all();
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
            'current_password' => 'required_with:password',
            'password' => 'nullable|string|min:8|confirmed',
        ];
        if ($profileFile || $storedFromPut) {
            $rules['profile_picture'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp';
        }
        $validator = Validator::make(array_merge($input, ['profile_picture' => $profileFile]), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        if ($request->filled('password')) {
            if (! Hash::check($request->input('current_password'), $user->password)) {
                return response()->json(['success' => false, 'errors' => ['current_password' => ['Current password is incorrect.']]], 422);
            }
            $user->password = Hash::make($request->input('password'));
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
        if ($profileFile && is_object($profileFile) && method_exists($profileFile, 'store')) {
            $stored = $profileFile->store('profiles', 'public');
            $user->profile_picture = $stored;
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        } elseif ($storedFromPut) {
            $user->profile_picture = $storedFromPut;
            ImageCompressionService::compressIfNeededFromPublicPath($storedFromPut);
        }
        $user->save();

        $employee = $user->employee;
        $user->refresh();
        $visitsCompleted = Visit::where('technician_id', $user->id)->where('status', 'completed')->count();
        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture' => $user->profile_picture,
            'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture) ?? $user->profile_picture_url,
            'employee_id' => $employee?->employee_id ?? ('TECH-' . $user->id),
            'rating' => 0,
            'jobs_completed' => $visitsCompleted,
            'total_earnings' => 0,
            'member_since' => $user->created_at?->toIso8601String(),
            'notification_preferences' => [
                'push_enabled' => true,
                'email_enabled' => true,
            ],
            'availability' => $user->technicianAvailability ? [
                'is_online' => $user->technicianAvailability->is_online,
                'auto_accept_jobs' => $user->technicianAvailability->auto_accept_jobs,
                'working_days' => $user->technicianAvailability->working_days ?? [],
                'working_hours_slots' => $user->technicianAvailability->working_hours_slots ?? [],
            ] : null,
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/technician/service-areas - Get technician's service area(s).
     * Returns: service_area (primary), service_areas (array).
     */
    public function getServiceAreas(Request $request)
    {
        $user = $request->user()->load('employee');
        $employee = $user->employee;
        [$serviceArea, $serviceAreas] = $this->resolveServiceAreaAndAreas($employee);
        return response()->json([
            'success' => true,
            'data' => [
                'service_area' => $serviceArea,
                'service_areas' => $serviceAreas,
            ],
        ]);
    }

    /**
     * PUT /api/technician/service-areas - Update technician's service area(s).
     * Body: form-data or JSON. service_area (string, optional), service_areas (array or JSON string or comma-separated, optional). Replaces existing.
     */
    public function updateServiceAreas(Request $request)
    {
        $user = $request->user()->load('employee');
        $request->validate([
            'service_area' => 'nullable|string|max:255',
            'service_areas' => 'nullable',
        ]);
        $employee = $user->employee;
        if (! $employee) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                ['employee_id' => 'TECH-' . $user->id]
            );
        }
        if ($request->has('service_areas') || $request->filled('service_areas')) {
            $raw = $request->input('service_areas');
            $arr = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?? array_map('trim', array_filter(explode(',', $raw)))) : []);
            if (! is_array($arr)) {
                $arr = [];
            }
            $employee->service_areas = array_values(array_filter(array_map('strval', $arr)));
            $employee->region = $employee->service_areas[0] ?? $employee->region;
        }
        if ($request->has('service_area') || $request->filled('service_area')) {
            $single = $request->input('service_area') ?: null;
            $employee->region = $single;
            if ($single && (empty($employee->service_areas) || ($employee->service_areas[0] ?? null) !== $single)) {
                $current = $employee->service_areas ?? [];
                $employee->service_areas = array_values(array_unique(array_merge([$single], $current)));
            }
        }
        $employee->save();
        [$serviceArea, $serviceAreas] = $this->resolveServiceAreaAndAreas($employee->refresh());
        return response()->json([
            'success' => true,
            'message' => 'Service areas updated.',
            'data' => [
                'service_area' => $serviceArea,
                'service_areas' => $serviceAreas,
            ],
        ]);
    }

    /**
     * GET /api/technician/specializations - Get technician's specializations (array).
     */
    public function getSpecializations(Request $request)
    {
        $user = $request->user()->load('employee');
        $employee = $user->employee;
        $specializations = $employee && is_array($employee->specializations)
            ? $employee->specializations
            : [];
        return response()->json([
            'success' => true,
            'data' => [
                'specializations' => $specializations,
            ],
        ]);
    }

    /**
     * PUT /api/technician/specializations - Update technician's specializations.
     * Body: form-data or JSON. specializations (array or JSON string or comma-separated). Replaces existing.
     * PUT with form-data: PHP does not populate $request->input(); parse raw multipart body.
     */
    public function updateSpecializations(Request $request)
    {
        $user = $request->user()->load('employee');
        $request->validate([
            'specializations' => 'nullable',
        ]);

        // POST form-data: PHP populates $request->input('specializations'). PUT/PATCH form-data: parse raw body.
        $raw = $request->input('specializations');
        $ct = (string) $request->header('Content-Type');
        $content = $request->getContent();

        if ($raw === null && $content !== '' && str_contains($ct, 'application/json')) {
            $decoded = json_decode($content, true);
            $raw = is_array($decoded) ? ($decoded['specializations'] ?? null) : null;
        }
        if ($raw === null && $content !== '' && str_contains($ct, 'multipart/form-data')) {
            $parsed = $this->parseMultipartFormData($content, $ct);
            $raw = $parsed['specializations'] ?? $parsed['specializations[]'] ?? null;
        }
        // Fallback: request bag may be populated by middleware or method spoofing (POST with _method=PUT)
        if ($raw === null) {
            $raw = $request->request->get('specializations');
        }

        $raw = is_string($raw) ? trim($raw) : $raw;
        $arr = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?? array_map('trim', array_filter(explode(',', $raw)))) : []);
        if (! is_array($arr)) {
            $arr = [];
        }
        $specializations = array_values(array_filter(array_map('strval', $arr)));

        $employee = $user->employee;
        if (! $employee) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                ['employee_id' => 'TECH-' . $user->id]
            );
        }
        $employee->specializations = $specializations;
        $employee->save();
        $employee->refresh();
        return response()->json([
            'success' => true,
            'message' => 'Specializations updated.',
            'data' => [
                'specializations' => $employee->specializations ?? [],
            ],
        ]);
    }

    /**
     * GET /api/technician/tasks - Single list of all tasks (visits). Use filter for scope.
     * filter: today|upcoming|completed|all|accepted|rejected.
     * today = open jobs (scheduled_date <= today). upcoming = open (scheduled_date > today).
     * completed = completed only. all = all open (pending, accepted, in_progress).
     * accepted = accepted + in_progress. rejected = rejected only.
     * Closed history (completed, rejected, cancelled) with summary: GET /api/technician/jobs.
     */
    public function tasks(Request $request)
    {
        $user = $request->user();
        $query = Visit::where('technician_id', $user->id)->with(['subscription.client', 'area']);
        $filter = $request->input('filter', 'all');
        if ($filter === 'today') {
            $query->whereDate('scheduled_date', '<=', Carbon::today())
                ->whereIn('status', $this->openJobStatuses());
        } elseif ($filter === 'upcoming') {
            $query->whereDate('scheduled_date', '>', Carbon::today())
                ->whereIn('status', $this->openJobStatuses());
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
        } elseif ($filter === 'accepted') {
            $query->whereIn('status', ['accepted', 'in_progress']);
        } elseif ($filter === 'rejected') {
            $query->where('status', 'rejected');
        } else {
            // all
            $query->whereIn('status', $this->openJobStatuses());
        }
        $query->orderBy('scheduled_date')->orderBy('id');
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);
        $items = $query->paginate($perPage);
        $items->getCollection()->transform(fn ($v) => $this->formatVisitAsTask($v));
        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * GET /api/technician/tasks/{id} - Single task (visit) detail.
     */
    public function taskShow(Request $request, $id)
    {
        $visit = Visit::where('technician_id', $request->user()->id)
            ->with(['subscription.client', 'area', 'photos'])
            ->find($id);
        if (!$visit) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $this->formatVisitAsTask($visit, true)]);
    }

    /**
     * GET /api/technician/tasks/{id}/detail - Mobile job details screen (rich payload: service_information, customer_information, before_after_photos, actions).
     */
    public function taskDetail(Request $request, $id)
    {
        $visit = Visit::where('technician_id', $request->user()->id)
            ->with(['subscription.client', 'area', 'photos'])
            ->find($id);
        if (! $visit) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $this->formatJobDetails($visit)]);
    }

    /**
     * PUT /api/technician/tasks/{id}/status - Update task status (start, complete, etc.).
     */
    public function taskUpdateStatus(Request $request, $id)
    {
        $visit = Visit::where('technician_id', $request->user()->id)->find($id);
        if (!$visit) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }
        $status = $request->input('status');
        $allowed = ['accepted', 'in_progress', 'completed'];
        if (!in_array($status, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Invalid status.'], 422);
        }
        $visit->status = $status;
        if ($status === 'in_progress') {
            $visit->started_at = $visit->started_at ?? now();
        } elseif ($status === 'completed') {
            $visit->completed_at = now();
            $visit->completed_date = Carbon::today();
            if ($request->has('notes')) {
                $visit->notes = $request->input('notes');
            }
        }
        $visit->save();
        return response()->json(['success' => true, 'data' => $this->formatVisitAsTask($visit->load('subscription.client', 'area'))]);
    }

    /**
     * GET /api/technician/jobs - Returns jobs in period: accepted, in_progress, completed, rejected, cancelled.
     * Query: period (week|month|year), per_page. Response: summary (total_earnings, jobs_completed, avg_rating) + paginated jobs.
     */
    public function jobs(Request $request)
    {
        $user = $request->user();
        $period = $request->input('period', 'month'); // week, month, year
        [$start, $end] = $this->resolvePeriodRange($period);
        $jobStatuses = ['accepted', 'in_progress', 'completed', 'rejected', 'cancelled'];
        $query = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$start, $end])
            ->whereIn('status', $jobStatuses);
        $completed = (clone $query)->where('status', 'completed')->count();
        $totalEarnings = 0; // stub
        $avgRating = 0; // stub
        $list = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$start, $end])
            ->whereIn('status', $jobStatuses)
            ->with(['subscription.client', 'area'])
            ->orderByDesc('scheduled_date')
            ->paginate((int) $request->input('per_page', 15));
        $list->getCollection()->transform(fn ($v) => $this->formatVisitAsTask($v));
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_earnings' => $totalEarnings,
                    'jobs_completed' => $completed,
                    'avg_rating' => $avgRating,
                ],
                'jobs' => $list,
            ],
        ]);
    }

    /**
     * GET /api/technician/jobs/accepted - Accepted and in-progress jobs (same as tasks?filter=accepted, under /jobs for frontend compatibility).
     * Query: period (week|month|year), per_page.
     */
    public function jobsAccepted(Request $request)
    {
        $user = $request->user();
        $period = $request->input('period', 'month');
        [$start, $end] = $this->resolvePeriodRange($period);
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $query = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$start, $end])
            ->whereIn('status', ['accepted', 'in_progress'])
            ->with(['subscription.client', 'area'])
            ->orderByDesc('scheduled_date');
        $items = $query->paginate($perPage);
        $items->getCollection()->transform(fn ($v) => $this->formatVisitAsTask($v));
        return response()->json([
            'success' => true,
            'message' => 'Accepted/In-Progress jobs list.',
            'data' => $items,
        ]);
    }

    /**
     * GET /api/technician/jobs/rejected - Rejected jobs only (same as tasks?filter=rejected, under /jobs for frontend compatibility).
     * Query: period (week|month|year), per_page.
     */
    public function jobsRejected(Request $request)
    {
        $user = $request->user();
        $period = $request->input('period', 'month');
        [$start, $end] = $this->resolvePeriodRange($period);
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $query = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$start, $end])
            ->where('status', 'rejected')
            ->with(['subscription.client', 'area'])
            ->orderByDesc('scheduled_date');
        $items = $query->paginate($perPage);
        $items->getCollection()->transform(fn ($v) => $this->formatVisitAsTask($v));
        return response()->json([
            'success' => true,
            'message' => 'Rejected jobs list.',
            'data' => $items,
        ]);
    }

    /**
     * GET /api/technician/jobs/status-counts - Counts for quick action tiles.
     */
    public function jobsStatusCounts(Request $request)
    {
        $user = $request->user();
        $period = $request->input('period', 'month');
        [$start, $end] = $this->resolvePeriodRange($period);

        $base = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$start, $end]);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'accepted' => (clone $base)->where('status', 'accepted')->count(),
                'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    /**
     * GET /api/technician/payout-summary - Balance, pending earnings, total earned (stub until payment).
     */
    public function payoutSummary(Request $request)
    {
        $user = $request->user();
        $completed = Visit::where('technician_id', $user->id)->where('status', 'completed')->count();
        return response()->json([
            'success' => true,
            'data' => [
                'available_balance' => 0,
                'pending_earnings' => 0,
                'total_earned' => 0,
                'jobs_completed' => $completed,
            ],
        ]);
    }

    /**
     * GET /api/technician/payouts - Payout history (stub).
     */
    public function payouts(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'data' => [],
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
        ]);
    }

    /**
     * GET /api/technician/settings/payout - Payout settings (default bank account, frequency).
     */
    public function payoutSettings(Request $request)
    {
        $user = $request->user();
        $av = $user->technicianAvailability;
        $defaultAccount = $av && $av->default_bank_account_id
            ? TechnicianBankAccount::where('user_id', $user->id)->find($av->default_bank_account_id)
            : null;
        return response()->json([
            'success' => true,
            'data' => [
                'default_bank_account_id' => $av?->default_bank_account_id,
                'default_bank_account' => $defaultAccount ? [
                    'id' => $defaultAccount->id,
                    'bank_name' => $defaultAccount->bank_name,
                    'masked_number' => $defaultAccount->masked_number,
                ] : null,
                'payout_frequency' => $av?->payout_frequency ?? 'monthly',
            ],
        ]);
    }

    /**
     * PUT /api/technician/settings/payout - Update payout settings.
     */
    public function updatePayoutSettings(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'default_bank_account_id' => 'nullable|integer|exists:technician_bank_accounts,id',
            'payout_frequency' => 'sometimes|string|in:weekly,biweekly,monthly',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $av = $user->technicianAvailability()->firstOrNew([]);
        $av->user_id = $user->id;
        if ($request->has('default_bank_account_id')) {
            $account = TechnicianBankAccount::where('user_id', $user->id)->find($request->input('default_bank_account_id'));
            $av->default_bank_account_id = $account ? $account->id : null;
        }
        if ($request->has('payout_frequency')) {
            $av->payout_frequency = $request->input('payout_frequency');
        }
        $av->save();
        return response()->json(['success' => true, 'data' => [
            'default_bank_account_id' => $av->default_bank_account_id,
            'payout_frequency' => $av->payout_frequency ?? 'monthly',
        ]]);
    }

    /**
     * GET /api/technician/bank-accounts - List bank accounts (masked).
     */
    public function bankAccounts(Request $request)
    {
        $accounts = TechnicianBankAccount::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('id')
            ->get();
        $data = $accounts->map(fn ($a) => [
            'id' => $a->id,
            'bank_name' => $a->bank_name,
            'account_holder_name' => $a->account_holder_name,
            'masked_number' => $a->masked_number,
            'last_four' => $a->last_four,
            'currency' => $a->currency,
            'is_default' => $a->is_default,
        ]);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * POST /api/technician/bank-accounts - Add bank account.
     */
    public function bankAccountStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'account_holder_name' => 'required|string|max:100',
            'last_four' => 'required|string|size:4',
            'currency' => 'nullable|string|size:3',
            'is_default' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $user = $request->user();
        $isDefault = $request->boolean('is_default', false);
        if ($isDefault) {
            TechnicianBankAccount::where('user_id', $user->id)->update(['is_default' => false]);
        }
        $account = TechnicianBankAccount::create([
            'user_id' => $user->id,
            'bank_name' => $request->input('bank_name'),
            'account_holder_name' => $request->input('account_holder_name'),
            'last_four' => $request->input('last_four'),
            'currency' => $request->input('currency', 'AED'),
            'is_default' => $isDefault,
        ]);
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $account->id,
                'bank_name' => $account->bank_name,
                'account_holder_name' => $account->account_holder_name,
                'masked_number' => $account->masked_number,
                'currency' => $account->currency,
                'is_default' => $account->is_default,
            ],
        ], 201);
    }

    /**
     * PUT /api/technician/bank-accounts/{id} - Update bank account.
     */
    public function bankAccountUpdate(Request $request, $id)
    {
        $account = TechnicianBankAccount::where('user_id', $request->user()->id)->find($id);
        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Bank account not found.'], 404);
        }
        $validator = Validator::make($request->all(), [
            'bank_name' => 'sometimes|string|max:100',
            'account_holder_name' => 'sometimes|string|max:100',
            'last_four' => 'sometimes|string|size:4',
            'currency' => 'nullable|string|size:3',
            'is_default' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        if ($request->boolean('is_default')) {
            TechnicianBankAccount::where('user_id', $request->user()->id)->where('id', '!=', $id)->update(['is_default' => false]);
        }
        $account->update($request->only(['bank_name', 'account_holder_name', 'last_four', 'currency', 'is_default']));
        return response()->json(['success' => true, 'data' => [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'account_holder_name' => $account->account_holder_name,
            'masked_number' => $account->masked_number,
            'currency' => $account->currency,
            'is_default' => $account->is_default,
        ]]);
    }

    /**
     * DELETE /api/technician/bank-accounts/{id} - Remove bank account.
     */
    public function bankAccountDestroy(Request $request, $id)
    {
        $account = TechnicianBankAccount::where('user_id', $request->user()->id)->find($id);
        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Bank account not found.'], 404);
        }
        $user = $request->user();
        $av = $user->technicianAvailability;
        if ($av && $av->default_bank_account_id == $account->id) {
            $av->update(['default_bank_account_id' => null]);
        }
        $account->delete();
        return response()->json(['success' => true, 'message' => 'Bank account removed.']);
    }

    /**
     * Leave reasons for vacation/leave form. Same list used in GET/PUT availability and GET leave-types.
     */
    private function getLeaveReasons(): array
    {
        return [
            ['value' => 'sick', 'label' => 'Sick leave'],
            ['value' => 'annual', 'label' => 'Annual Leave'],
            ['value' => 'unpaid', 'label' => 'Unpaid leave'],
            ['value' => 'paternity', 'label' => 'Paternity Leave'],
            ['value' => 'other', 'label' => 'Other', 'requires_notes' => true],
        ];
    }

    /**
     * GET /api/technician/leave-types
     * Returns the list of leave types for the technician leave/vacation form. Use in dropdown; send selected label as vacations[].leave_type in PUT availability. Optional text as vacations[].reason.
     */
    public function leaveTypes(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->getLeaveReasons()]);
    }

    /**
     * GET /api/technician/availability
     * Returns: is_online, auto_accept_jobs, working_days, working_hours_slots, service_area, service_areas, breaks, vacations.
     */
    public function availability(Request $request)
    {
        $user = $request->user()->load('employee');
        $av = $user->technicianAvailability;
        $breaksQuery = TechnicianBreak::where('user_id', $user->id)->orderBy('date')->orderBy('start_time');
        if ($request->filled('from') && $request->filled('to')) {
            $breaksQuery->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }
        $breaks = $breaksQuery->get();

        $vacationsQuery = TechnicianVacation::where('user_id', $user->id)->orderBy('start_date');
        if ($request->filled('from') && $request->filled('to')) {
            $vacationsQuery->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->input('from'), $request->input('to')])
                    ->orWhereBetween('end_date', [$request->input('from'), $request->input('to')]);
            });
        }
        $vacations = $vacationsQuery->get();

        [$serviceArea, $serviceAreas] = $this->resolveServiceAreaAndAreas($user->employee);
        $data = [
            'is_online' => $av?->is_online ?? true,
            'auto_accept_jobs' => $av?->auto_accept_jobs ?? false,
            'working_days' => $av?->working_days ?? [],
            'working_hours_slots' => $av?->working_hours_slots ?? [],
            'service_area' => $serviceArea,
            'service_areas' => $serviceAreas,
            'breaks' => $breaks->map(fn ($b) => [
                'id' => $b->id,
                'date' => $b->date?->toDateString(),
                'start_time' => $b->start_time,
                'end_time' => $b->end_time,
                'reason' => $b->reason,
            ])->values()->all(),
            'vacations' => $vacations->map(fn ($v) => [
                'id' => $v->id,
                'start_date' => $v->start_date?->toDateString(),
                'end_date' => $v->end_date?->toDateString(),
                'leave_type' => $v->leave_type,
                'reason' => $v->reason,
            ])->values()->all(),
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PUT /api/technician/availability
     * Accepts form-data or JSON: is_online, auto_accept_jobs, working_days, working_hours_slots, service_area, service_areas, breaks, vacations.
     * Sending breaks or vacations replaces all existing. For form-data: working_days, working_hours_slots, breaks, vacations can be JSON strings.
     */
    public function updateAvailability(Request $request)
    {
        $user = $request->user();
        $input = $this->normalizeAvailabilityInput($request);

        $rules = [
            'is_online' => 'sometimes|boolean',
            'auto_accept_jobs' => 'sometimes|boolean',
            'working_days' => 'sometimes|array',
            'working_days.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
            'working_hours_slots' => 'sometimes|array',
            'working_hours_slots.*.slot' => 'string',
            'working_hours_slots.*.start' => 'string',
            'working_hours_slots.*.end' => 'string',
            'service_area' => 'nullable|string|max:255',
            'service_areas' => 'sometimes|array',
            'service_areas.*' => 'string|max:255',
            'breaks' => 'sometimes|array',
            'breaks.*.date' => 'required|date',
            'breaks.*.start_time' => 'required|string',
            'breaks.*.end_time' => 'required|string',
            'breaks.*.reason' => 'nullable|string|max:255',
            'vacations' => 'sometimes|array',
            'vacations.*.start_date' => 'required|date',
            'vacations.*.end_date' => 'required|date',
            'vacations.*.leave_type' => 'nullable|string|max:255',
            'vacations.*.reason' => 'nullable|string|max:1000',
        ];
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $av = $user->technicianAvailability()->firstOrNew([]);
        $av->user_id = $user->id;
        foreach (['is_online', 'auto_accept_jobs', 'working_days', 'working_hours_slots'] as $key) {
            if (array_key_exists($key, $input)) {
                $av->$key = $input[$key];
            }
        }
        $av->save();

        if (array_key_exists('service_area', $input) || array_key_exists('service_areas', $input)) {
            $employee = $user->employee ?? Employee::firstOrCreate(
                ['user_id' => $user->id],
                ['employee_id' => 'TECH-' . $user->id]
            );
            if (array_key_exists('service_areas', $input)) {
                $arr = $input['service_areas'];
                $employee->service_areas = array_values(array_filter(array_map('strval', $arr)));
                $employee->region = $employee->service_areas[0] ?? $employee->region;
            }
            if (array_key_exists('service_area', $input)) {
                $single = $input['service_area'] ?: null;
                $employee->region = $single;
                if ($single) {
                    $current = $employee->service_areas ?? [];
                    if (empty($current) || $current[0] !== $single) {
                        $employee->service_areas = array_values(array_unique(array_merge([$single], $current)));
                    }
                }
            }
            $employee->save();
        }

        if (array_key_exists('breaks', $input)) {
            TechnicianBreak::where('user_id', $user->id)->delete();
            foreach ($input['breaks'] ?? [] as $item) {
                TechnicianBreak::create([
                    'user_id' => $user->id,
                    'date' => $item['date'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'reason' => $item['reason'] ?? null,
                ]);
            }
        }

        if (array_key_exists('vacations', $input)) {
            TechnicianVacation::where('user_id', $user->id)->delete();
            foreach ($input['vacations'] ?? [] as $item) {
                TechnicianVacation::create([
                    'user_id' => $user->id,
                    'start_date' => $item['start_date'],
                    'end_date' => $item['end_date'],
                    'leave_type' => $item['leave_type'] ?? null,
                    'reason' => $item['reason'] ?? null,
                ]);
            }
        }

        $user->load('employee');
        $av = $av->fresh();
        $breaks = TechnicianBreak::where('user_id', $user->id)->orderBy('date')->orderBy('start_time')->get();
        $vacations = TechnicianVacation::where('user_id', $user->id)->orderBy('start_date')->get();
        [$serviceArea, $serviceAreas] = $this->resolveServiceAreaAndAreas($user->employee);
        $data = [
            'is_online' => $av->is_online ?? true,
            'auto_accept_jobs' => $av->auto_accept_jobs ?? false,
            'working_days' => $av->working_days ?? [],
            'working_hours_slots' => $av->working_hours_slots ?? [],
            'service_area' => $serviceArea,
            'service_areas' => $serviceAreas,
            'breaks' => $breaks->map(fn ($b) => [
                'id' => $b->id,
                'date' => $b->date?->toDateString(),
                'start_time' => $b->start_time,
                'end_time' => $b->end_time,
                'reason' => $b->reason,
            ])->values()->all(),
            'vacations' => $vacations->map(fn ($v) => [
                'id' => $v->id,
                'start_date' => $v->start_date?->toDateString(),
                'end_date' => $v->end_date?->toDateString(),
                'leave_type' => $v->leave_type,
                'reason' => $v->reason,
            ])->values()->all(),
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/technician/schedule - Calendar view (date range: tasks + availability).
     */
    public function schedule(Request $request)
    {
        $user = $request->user();
        $from = $request->input('from', Carbon::now()->startOfWeek()->toDateString());
        $to = $request->input('to', Carbon::now()->endOfWeek()->toDateString());
        $visits = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$from, $to])
            ->with(['subscription.client', 'area'])
            ->orderBy('scheduled_date')
            ->get();
        $breaks = TechnicianBreak::where('user_id', $user->id)->whereBetween('date', [$from, $to])->get();
        $vacations = TechnicianVacation::where('user_id', $user->id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])->orWhereBetween('end_date', [$from, $to]);
            })
            ->get();
        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $visits->map(fn ($v) => $this->formatVisitAsTask($v)),
                'breaks' => $breaks,
                'vacations' => $vacations,
            ],
        ]);
    }

    /**
     * GET /api/technician/notifications - List notifications (published tips from admin), same as user dashboard.
     * Each item: id, type: tip, title, message, created_at. Paginated.
     */
    public function getNotifications(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 100);

        $tips = Tip::where('status', 'published')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = $tips->through(fn (Tip $tip) => [
            'id' => $tip->id,
            'type' => 'tip',
            'title' => $tip->title,
            'message' => $tip->content,
            'created_at' => $tip->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success('Notifications retrieved successfully.', $data);
    }

    /**
     * POST /api/technician/notifications/{id}/read - Mark one as read. Id = tip id (same as user dashboard; no stored state).
     */
    public function markNotificationRead(Request $request, string $id)
    {
        if (is_numeric($id)) {
            $exists = Tip::where('status', 'published')->where('id', (int) $id)->exists();
            if ($exists) {
                return ApiResponse::success('Notification marked as read.');
            }
        }
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }
        $notification->markAsRead();
        return ApiResponse::success('Notification marked as read.');
    }

    /**
     * POST /api/technician/notifications/read-all - Mark all as read (same behaviour as user dashboard).
     */
    public function markAllNotificationsRead(Request $request)
    {
        $request->user()->unreadNotifications->each(fn ($n) => $n->markAsRead());
        return ApiResponse::success('All notifications marked as read.');
    }

    /**
     * POST /api/technician/notifications/clear-all - Clear all (for "Clear All" button, same as user dashboard).
     * Marks all as read; returns success so the app can clear or refresh the list.
     */
    public function clearAllNotifications(Request $request)
    {
        $request->user()->unreadNotifications->each(fn ($n) => $n->markAsRead());
        return ApiResponse::success('All notifications cleared.');
    }

    /**
     * POST /api/technician/reports
     * Technician-only: submit field report to supervisor (Job Details → "Submit Field Report to Supervisor").
     * Accepts form-data: visit_id, technician_notes, recommended_products (array), before_photo (file), after_photo (file).
     */
    public function submitReport(Request $request)
    {
        $rules = [
            'visit_id' => 'required|integer|exists:visits,id',
            'technician_notes' => 'nullable|string|max:10000',
            'notes' => 'nullable|string|max:5000',
            'before_photo' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:10240',
            'after_photo' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:10240',
        ];
        $data = $request->validate($rules);

        $user = $request->user();
        $visit = Visit::find($data['visit_id']);

        if ($visit->technician_id !== (int) $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You can only submit reports for visits assigned to you.',
            ], 403);
        }

        if ($visit->status !== 'completed') {
            return response()->json([
                'status' => false,
                'message' => 'You can only submit a report for a completed visit. Complete the visit first.',
            ], 422);
        }

        if ($visit->report) {
            return response()->json([
                'status' => false,
                'message' => 'A report already exists for this visit.',
            ], 422);
        }

        $report = Report::create([
            'visit_id' => $visit->id,
            'technician_notes' => $data['technician_notes'] ?? '',
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        foreach (['before_photo' => 'before', 'after_photo' => 'after'] as $key => $type) {
            if (! $request->hasFile($key)) {
                continue;
            }
            $file = $request->file($key);
            $path = $file->store('visit_photos', 'public');
            ImageCompressionService::compressIfNeededFromPublicPath($path);
            VisitPhoto::create([
                'visit_id' => $visit->id,
                'photo_path' => $path,
                'type' => $type,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Report submitted to supervisor successfully.',
            'data' => $report->load(['visit', 'visit.photos']),
        ], 201);
    }

    private function formatJobDetails(Visit $visit): array
    {
        $client = $visit->subscription?->client;
        $meta = $this->parseVisitMetaFromNotes((string) ($visit->notes ?? ''));
        $scheduledAt = $visit->started_at ?? ($visit->scheduled_date ? Carbon::parse($visit->scheduled_date)->setTime(8, 0) : null);
        $duration = $meta['duration_minutes'] ?? null;
        if ($duration === null && $visit->started_at && $visit->completed_at) {
            $duration = (int) $visit->started_at->diffInMinutes($visit->completed_at);
        }

        $photos = $visit->photos?->map(fn ($p) => [
            'id' => $p->id,
            'type' => $p->type ?? 'after',
            'photo_url' => $p->photo_path ? (request()->getSchemeAndHttpHost() . '/storage/' . $p->photo_path) : null,
        ]) ?? collect();

        return [
            'job_id' => $visit->id,
            'job_number' => 'job_' . str_pad((string) $visit->id, 3, '0', STR_PAD_LEFT),
            'status' => $visit->status,
            'date' => $visit->scheduled_date?->toDateString(),
            'service_information' => [
                'title' => $meta['service_name'] ?? ($visit->subscription?->plan ? str_replace('_', ' ', (string) $visit->subscription->plan) : 'Service Visit'),
                'description' => 'Visit details for technician execution.',
                'time' => $scheduledAt?->format('g:i A'),
                'duration_minutes' => $duration,
                'price' => $meta['price'] ?? null,
                'price_display' => $meta['price_display'] ?? null,
            ],
            'customer_information' => [
                'name' => $meta['farm_name'] ?? $client?->name,
                'phone' => $client?->phone,
                'email' => $client?->email,
            ],
            'service_address' => [
                'label' => 'Service Location',
                'address' => $meta['location'] ?? $visit->area?->name,
                'get_directions' => true,
            ],
            'special_instructions' => null,
            'field_notes' => $visit->notes,
            'before_after_photos' => [
                'before' => $photos->where('type', 'before')->values(),
                'after' => $photos->where('type', 'after')->values(),
                'other' => $photos->whereNotIn('type', ['before', 'after'])->values(),
            ],
            'actions' => [
                'can_submit_field_report' => in_array($visit->status, ['accepted', 'in_progress', 'completed'], true),
                'can_complete_visit' => in_array($visit->status, ['accepted', 'in_progress'], true),
                'can_call_customer' => ! empty($client?->phone),
            ],
        ];
    }

    private function normalizeAvailabilityInput(Request $request): array
    {
        $out = [];
        $contentType = (string) $request->header('Content-Type');

        // PUT with multipart/form-data: PHP does not populate $request->input() from body; parse raw content
        if (in_array(strtoupper($request->method()), ['PUT', 'PATCH'], true) && str_contains($contentType, 'multipart/form-data')) {
            $parsed = $this->parseMultipartFormData($request->getContent(), $contentType);
            if (! empty($parsed)) {
                $out = $parsed;
            }
        }

        // When Content-Type is application/json, parse body so breaks/arrays come through
        if (str_contains($contentType, 'application/json') && $request->getContent() !== '') {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $out = array_merge($out, $decoded);
            }
        }

        // Overlay form fields from $request->input() (works for POST and x-www-form-urlencoded)
        if ($request->has('is_online')) {
            $out['is_online'] = filter_var($request->input('is_online'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('auto_accept_jobs')) {
            $out['auto_accept_jobs'] = filter_var($request->input('auto_accept_jobs'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('working_days')) {
            $v = $request->input('working_days');
            $out['working_days'] = is_array($v) ? $v : (is_string($v) ? json_decode($v, true) : []);
            if (! is_array($out['working_days'])) {
                $out['working_days'] = [];
            }
        }
        if ($request->has('working_hours_slots')) {
            $v = $request->input('working_hours_slots');
            $out['working_hours_slots'] = is_array($v) ? $v : (is_string($v) ? json_decode($v, true) : []);
            if (! is_array($out['working_hours_slots'])) {
                $out['working_hours_slots'] = [];
            }
        }
        if ($request->has('service_area') || $request->filled('service_area')) {
            $out['service_area'] = $request->input('service_area') ?: null;
        }
        if ($request->has('service_areas')) {
            $v = $request->input('service_areas');
            $out['service_areas'] = is_array($v) ? $v : (is_string($v) ? (json_decode(trim($v), true) ?? []) : []);
        }
        if ($request->has('breaks')) {
            $v = $request->input('breaks');
            if (is_array($v)) {
                $out['breaks'] = $v;
            } elseif (is_string($v)) {
                $decoded = json_decode(trim($v), true);
                $out['breaks'] = is_array($decoded) ? $decoded : [];
            } else {
                $out['breaks'] = [];
            }
        }
        if ($request->has('vacations')) {
            $v = $request->input('vacations');
            if (is_array($v)) {
                $out['vacations'] = $v;
            } elseif (is_string($v)) {
                $decoded = json_decode(trim($v), true);
                $out['vacations'] = is_array($decoded) ? $decoded : [];
            } else {
                $out['vacations'] = [];
            }
        }

        // Normalize when data came from multipart (all values are strings)
        if (isset($out['is_online']) && is_string($out['is_online'])) {
            $out['is_online'] = filter_var($out['is_online'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($out['auto_accept_jobs']) && is_string($out['auto_accept_jobs'])) {
            $out['auto_accept_jobs'] = filter_var($out['auto_accept_jobs'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($out['working_days'])) {
            $v = $out['working_days'];
            $out['working_days'] = is_array($v) ? $v : (is_string($v) ? (json_decode(trim($v), true) ?? []) : []);
        }
        if (isset($out['working_hours_slots'])) {
            $v = $out['working_hours_slots'];
            $out['working_hours_slots'] = is_array($v) ? $v : (is_string($v) ? (json_decode(trim($v), true) ?? []) : []);
        }
        if (isset($out['breaks'])) {
            $v = $out['breaks'];
            $out['breaks'] = is_array($v) ? $v : (is_string($v) ? (json_decode(trim($v), true) ?? []) : []);
        }
        if (isset($out['vacations'])) {
            $v = $out['vacations'];
            $out['vacations'] = is_array($v) ? $v : (is_string($v) ? (json_decode(trim($v), true) ?? []) : []);
        }
        if (isset($out['service_areas'])) {
            $v = $out['service_areas'];
            $arr = is_array($v) ? $v : (is_string($v) ? (json_decode(trim($v), true) ?? []) : []);
            $out['service_areas'] = array_values(array_filter(array_map('strval', (array) $arr)));
        }

        return $out;
    }

    /**
     * @return array{0: string|null, 1: array}
     */
    private function resolveServiceAreaAndAreas(?Employee $employee): array
    {
        if (! $employee) {
            return [null, []];
        }
        $areas = $employee->service_areas ?? [];
        if (! is_array($areas)) {
            $areas = [];
        }
        if (empty($areas) && $employee->region) {
            $areas = [$employee->region];
        }
        $first = $areas[0] ?? $employee->region;

        return [$first, array_values($areas)];
    }

    /**
     * Parse multipart/form-data raw body (used for PUT/PATCH when PHP does not populate $_POST).
     */
    private function parseMultipartFormData(string $content, string $contentType): array
    {
        if ($content === '') {
            return [];
        }
        if (! preg_match('/boundary=(?:["\'])?([^"\'; \n]+)/', $contentType, $m)) {
            return [];
        }
        $boundary = trim($m[1], " \t\r\n\"';");
        $delim = '--' . $boundary;
        $parts = array_slice(preg_split('/' . preg_quote($delim, '/') . '\r?\n?/', $content), 1, -1);
        $result = [];
        foreach ($parts as $part) {
            $part = trim($part, "\r\n");
            if ($part === '' || ! str_contains($part, "\r\n\r\n") && ! str_contains($part, "\n\n")) {
                continue;
            }
            $split = preg_split('/\r?\n\r?\n/', $part, 2);
            $rawHeaders = $split[0] ?? '';
            $value = isset($split[1]) ? trim(preg_replace('/\r?\n$/', '', $split[1])) : '';
            $name = null;
            foreach (preg_split('/\r?\n/', $rawHeaders) as $header) {
                if (stripos($header, 'Content-Disposition:') === 0) {
                    if (preg_match('/name="([^"]+)"/', $header, $nm)) {
                        $name = $nm[1];
                    } elseif (preg_match('/name=([^";\s]+)/', $header, $nm)) {
                        $name = trim($nm[1], '"\'');
                    }
                    break;
                }
            }
            if ($name !== null && $name !== '') {
                $result[$name] = $value;
            }
        }
        return $result;
    }

    private function openJobStatuses(): array
    {
        return ['pending', 'accepted', 'in_progress'];
    }

    private function closedJobStatuses(): array
    {
        return ['completed', 'rejected', 'cancelled'];
    }

    private function resolvePeriodRange(string $period): array
    {
        $start = match ($period) {
            'week' => Carbon::now()->startOfWeek(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };
        $end = match ($period) {
            'week' => Carbon::now()->endOfWeek(),
            'year' => Carbon::now()->endOfYear(),
            default => Carbon::now()->endOfMonth(),
        };
        // Use date strings so whereBetween('scheduled_date', ...) matches DATE column reliably
        return [$start->toDateString(), $end->toDateString()];
    }

    private function formatVisitAsTask(Visit $visit, bool $includeDetail = false): array
    {
        $client = $visit->subscription?->client;
        $meta = $this->parseVisitMetaFromNotes((string) ($visit->notes ?? ''));
        $scheduledAt = $visit->started_at ?? ($visit->scheduled_date ? Carbon::parse($visit->scheduled_date)->setTime(8, 0) : null);
        $duration = $meta['duration_minutes'] ?? null;
        if ($duration === null && $visit->started_at && $visit->completed_at) {
            $duration = (int) $visit->started_at->diffInMinutes($visit->completed_at);
        }

        $base = [
            'id' => $visit->id,
            'scheduled_date' => $visit->scheduled_date?->toDateString(),
            'scheduled_time' => $scheduledAt?->format('g:i A'),
            'status' => $visit->status,
            'farm_name' => $meta['farm_name'] ?? $client?->name,
            'service_name' => $meta['service_name'] ?? ($visit->subscription?->plan ? str_replace('_', ' ', (string) $visit->subscription->plan) : null),
            'location' => $meta['location'] ?? $visit->area?->name,
            'duration_minutes' => $duration,
            'client_name' => $client?->name,
            'client_id' => $client?->id,
            'area' => $visit->area?->name,
            'accepted_at' => $visit->accepted_at?->toIso8601String(),
            'started_at' => $visit->started_at?->toIso8601String(),
            'completed_at' => $visit->completed_at?->toIso8601String(),
        ];
        if ($includeDetail) {
            $base['notes'] = $visit->notes;
            $base['metadata'] = $meta;
            $base['subscription'] = $visit->subscription ? [
                'id' => $visit->subscription->id,
                'plan' => $visit->subscription->plan,
                'client' => $client ? ['id' => $client->id, 'name' => $client->name, 'email' => $client->email, 'phone' => $client->phone] : null,
            ] : null;
            $base['photos'] = $visit->photos?->map(fn ($p) => [
                'id' => $p->id,
                'photo_url' => $p->photo_path ? (request()->getSchemeAndHttpHost() . '/storage/' . $p->photo_path) : null,
                'type' => $p->type ?? 'after',
            ]) ?? [];
        }
        return $base;
    }

    private function formatVisitAsRecentVisit(Visit $visit): array
    {
        $meta = $this->parseVisitMetaFromNotes((string) ($visit->notes ?? ''));

        return [
            'id' => $visit->id,
            'farm_name' => $meta['farm_name'] ?? ($visit->subscription?->client?->name ?? 'Visit #' . $visit->id),
            'service_name' => $meta['service_name'] ?? null,
            'date' => $visit->completed_date?->toDateString() ?? $visit->scheduled_date?->toDateString(),
            'price' => $meta['price'] ?? 0,
            'price_display' => $meta['price_display'] ?? null,
            'rating' => $meta['rating'] ?? null,
        ];
    }

    /**
     * Parse seeded note format:
     * [DUMMY-SUP-ASSIGN] Farm | Service | Location | 120 min | AED 289.99 | 5/5
     */
    private function parseVisitMetaFromNotes(string $notes): array
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        if ($clean === '') {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));
        $meta = [];

        if (isset($parts[0])) {
            $meta['farm_name'] = $parts[0];
        }
        if (isset($parts[1])) {
            $meta['service_name'] = $parts[1];
        }
        if (isset($parts[2])) {
            $meta['location'] = $parts[2];
        }
        if (isset($parts[3]) && preg_match('/(\d+)\s*min/i', $parts[3], $m)) {
            $meta['duration_minutes'] = (int) $m[1];
        }
        if (isset($parts[4]) && preg_match('/AED\s*([0-9]+(?:\.[0-9]+)?)/i', $parts[4], $m)) {
            $meta['price'] = (float) $m[1];
            $meta['price_display'] = 'AED ' . $m[1];
        }
        if (isset($parts[5]) && preg_match('/([0-9]+(?:\.[0-9]+)?)\s*\/\s*5/', $parts[5], $m)) {
            $meta['rating'] = (float) $m[1];
        } elseif (isset($parts[3]) && preg_match('/([0-9]+(?:\.[0-9]+)?)\s*\/\s*5/', $parts[3], $m)) {
            $meta['rating'] = (float) $m[1];
        }

        return $meta;
    }
}
