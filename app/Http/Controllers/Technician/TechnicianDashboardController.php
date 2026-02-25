<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\ImageCompressionService;
use App\Models\TechnicianAvailability;
use App\Models\TechnicianBankAccount;
use App\Models\TechnicianBreak;
use App\Models\TechnicianVacation;
use App\Models\Visit;
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
            ->whereDate('scheduled_date', Carbon::today())
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
                'profile_picture_url' => $user->profile_picture_url,
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
     * GET /api/technician/profile - Full profile (personal info, service areas, skills, notification prefs).
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
            'profile_picture_url' => $user->profile_picture_url,
            'employee_id' => $employee?->employee_id ?? ('TECH-' . $user->id),
            'rating' => 0,
            'jobs_completed' => $visitsCompleted,
            'total_earnings' => 0,
            'member_since' => $user->created_at?->toIso8601String(),
            'specializations' => $employee?->specializations ?? [],
            'service_area' => $employee?->region,
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
     * Accepts multipart/form-data: name, email, phone, service_area, specializations (JSON array string),
     * current_password, password, password_confirmation, profile_picture (file). All optional.
     * Returns full profile (same shape as GET /api/technician/profile).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user()->load('employee', 'technicianAvailability');

        // Resolve single file (Postman may send multiple files as array)
        $profileFile = $request->file('profile_picture');
        if (is_array($profileFile)) {
            $profileFile = $profileFile[0] ?? null;
        }
        $input = $request->all();
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
            'service_area' => 'nullable|string|max:255',
            'specializations' => 'nullable',
            'current_password' => 'required_with:password',
            'password' => 'nullable|string|min:8|confirmed',
        ];
        if ($profileFile) {
            $rules['profile_picture'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:20480';
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
        if ($profileFile) {
            $stored = $profileFile->store('profiles', 'public');
            $user->profile_picture = $stored;
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        }
        $user->save();

        // Get or create employee so technician can set service_area and specializations
        $employee = $user->employee;
        if (! $employee) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                ['employee_id' => 'TECH-' . $user->id]
            );
        }
        if ($request->has('service_area') || $request->filled('service_area')) {
            $employee->region = $request->input('service_area') ?: null;
        }
        if ($request->has('specializations') || $request->filled('specializations')) {
            $raw = $request->input('specializations');
            $arr = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : []);
            if (! is_array($arr) && is_string($raw)) {
                $arr = array_map('trim', explode(',', $raw));
            }
            $employee->specializations = array_values(array_filter(array_map('strval', (array) ($arr ?? []))));
        }
        $employee->save();
        $employee->refresh();
        $user->setRelation('employee', $employee);
        $user->refresh();
        $visitsCompleted = Visit::where('technician_id', $user->id)->where('status', 'completed')->count();
        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture' => $user->profile_picture,
            'profile_picture_url' => $user->profile_picture_url,
            'employee_id' => $employee?->employee_id ?? ('TECH-' . $user->id),
            'rating' => 0,
            'jobs_completed' => $visitsCompleted,
            'total_earnings' => 0,
            'member_since' => $user->created_at?->toIso8601String(),
            'specializations' => $employee?->specializations ?? [],
            'service_area' => $employee?->region,
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
     * GET /api/technician/tasks - List tasks (visits) with filter=today|upcoming|completed and pagination.
     */
    public function tasks(Request $request)
    {
        $user = $request->user();
        $query = Visit::where('technician_id', $user->id)->with(['subscription.client', 'area']);
        $filter = $request->input('filter', 'all');
        if ($filter === 'today') {
            $query->whereDate('scheduled_date', Carbon::today());
        } elseif ($filter === 'upcoming') {
            $query->where('scheduled_date', '>=', Carbon::today())->whereIn('status', ['pending', 'accepted']);
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
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
     * GET /api/technician/jobs - Job history (visits) with summary KPIs, pagination.
     */
    public function jobs(Request $request)
    {
        $user = $request->user();
        $period = $request->input('period', 'month'); // week, month, year
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
        $query = Visit::where('technician_id', $user->id)->whereBetween('scheduled_date', [$start, $end]);
        $completed = (clone $query)->where('status', 'completed')->count();
        $totalEarnings = 0; // stub
        $avgRating = 0; // stub
        $list = Visit::where('technician_id', $user->id)
            ->whereBetween('scheduled_date', [$start, $end])
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
     * GET /api/technician/jobs/{id} - Single job (visit) detail.
     */
    public function jobShow(Request $request, $id)
    {
        $visit = Visit::where('technician_id', $request->user()->id)
            ->with(['subscription.client', 'area', 'photos'])
            ->find($id);
        if (!$visit) {
            return response()->json(['success' => false, 'message' => 'Job not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $this->formatVisitAsTask($visit, true)]);
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
     * GET /api/technician/availability
     */
    public function availability(Request $request)
    {
        $user = $request->user();
        $av = $user->technicianAvailability;
        $data = [
            'is_online' => $av?->is_online ?? true,
            'auto_accept_jobs' => $av?->auto_accept_jobs ?? false,
            'working_days' => $av?->working_days ?? [],
            'working_hours_slots' => $av?->working_hours_slots ?? [],
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PUT /api/technician/availability
     */
    public function updateAvailability(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'is_online' => 'sometimes|boolean',
            'auto_accept_jobs' => 'sometimes|boolean',
            'working_days' => 'sometimes|array',
            'working_days.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
            'working_hours_slots' => 'sometimes|array',
            'working_hours_slots.*.slot' => 'string',
            'working_hours_slots.*.start' => 'string',
            'working_hours_slots.*.end' => 'string',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $av = $user->technicianAvailability()->firstOrNew([]);
        $av->user_id = $user->id;
        foreach (['is_online', 'auto_accept_jobs', 'working_days', 'working_hours_slots'] as $key) {
            if ($request->has($key)) {
                $av->$key = $request->input($key);
            }
        }
        $av->save();
        return response()->json(['success' => true, 'data' => $av->fresh()]);
    }

    /**
     * GET /api/technician/breaks
     */
    public function breaks(Request $request)
    {
        $query = TechnicianBreak::where('user_id', $request->user()->id)->orderBy('date')->orderBy('start_time');
        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }
        $items = $query->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * POST /api/technician/breaks
     */
    public function breakStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'reason' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $break = TechnicianBreak::create([
            'user_id' => $request->user()->id,
            'date' => $request->input('date'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'reason' => $request->input('reason'),
        ]);
        return response()->json(['success' => true, 'data' => $break], 201);
    }

    /**
     * PUT /api/technician/breaks/{id}
     */
    public function breakUpdate(Request $request, $id)
    {
        $break = TechnicianBreak::where('user_id', $request->user()->id)->find($id);
        if (!$break) {
            return response()->json(['success' => false, 'message' => 'Break not found.'], 404);
        }
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date',
            'start_time' => 'sometimes|string',
            'end_time' => 'sometimes|string',
            'reason' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $break->update($request->only(['date', 'start_time', 'end_time', 'reason']));
        return response()->json(['success' => true, 'data' => $break->fresh()]);
    }

    /**
     * DELETE /api/technician/breaks/{id}
     */
    public function breakDestroy(Request $request, $id)
    {
        $break = TechnicianBreak::where('user_id', $request->user()->id)->find($id);
        if (!$break) {
            return response()->json(['success' => false, 'message' => 'Break not found.'], 404);
        }
        $break->delete();
        return response()->json(['success' => true, 'message' => 'Break deleted.']);
    }

    /**
     * GET /api/technician/vacations
     */
    public function vacations(Request $request)
    {
        $query = TechnicianVacation::where('user_id', $request->user()->id)->orderBy('start_date');
        if ($request->has('from') && $request->has('to')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->input('from'), $request->input('to')])
                    ->orWhereBetween('end_date', [$request->input('from'), $request->input('to')]);
            });
        }
        $items = $query->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * POST /api/technician/vacations
     */
    public function vacationStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $vacation = TechnicianVacation::create([
            'user_id' => $request->user()->id,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'reason' => $request->input('reason'),
        ]);
        return response()->json(['success' => true, 'data' => $vacation], 201);
    }

    /**
     * PUT /api/technician/vacations/{id}
     */
    public function vacationUpdate(Request $request, $id)
    {
        $vacation = TechnicianVacation::where('user_id', $request->user()->id)->find($id);
        if (!$vacation) {
            return response()->json(['success' => false, 'message' => 'Vacation not found.'], 404);
        }
        $data = $request->only(['start_date', 'end_date', 'reason']);
        $start = $data['start_date'] ?? $vacation->start_date?->toDateString();
        $end = $data['end_date'] ?? $vacation->end_date?->toDateString();
        $validator = Validator::make(array_merge($data, ['start_date' => $start, 'end_date' => $end]), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        if (isset($data['start_date'])) {
            $vacation->start_date = $data['start_date'];
        }
        if (isset($data['end_date'])) {
            $vacation->end_date = $data['end_date'];
        }
        if (array_key_exists('reason', $data)) {
            $vacation->reason = $data['reason'];
        }
        $vacation->save();
        return response()->json(['success' => true, 'data' => $vacation->fresh()]);
    }

    /**
     * DELETE /api/technician/vacations/{id}
     */
    public function vacationDestroy(Request $request, $id)
    {
        $vacation = TechnicianVacation::where('user_id', $request->user()->id)->find($id);
        if (!$vacation) {
            return response()->json(['success' => false, 'message' => 'Vacation not found.'], 404);
        }
        $vacation->delete();
        return response()->json(['success' => true, 'message' => 'Vacation deleted.']);
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
