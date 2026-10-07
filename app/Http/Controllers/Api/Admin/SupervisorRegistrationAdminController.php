<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SupervisorRegistration;
use App\Services\Supervisor\SupervisorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SupervisorRegistrationAdminController extends Controller
{
    public function __construct(
        private readonly SupervisorRegistrationService $registration
    ) {}

    /**
     * Contractor Management screen — summary chips + filtered list.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->statusCounts();
        // List cards do not need documents — only user.status for display chips.
        $query = SupervisorRegistration::query()
            ->with(['user:id,status'])
            ->latest('id');

        $this->applyUiStatusFilter($query, $request->query('status'));
        $this->applySearch($query, $request->query('search'));

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $page = $query->paginate($perPage);

        return ApiResponse::success('Contractor registrations retrieved.', [
            'summary' => [
                'pending' => $filters['pending'],
                'active' => $filters['active'],
                'total' => $filters['all'],
            ],
            'filters' => $filters,
            'items' => collect($page->items())
                ->map(fn (SupervisorRegistration $r) => $r->toAdminListArray())
                ->values(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'settings_link' => [
                'label' => 'Categories / Services / Locations',
                'endpoint' => '/api/admin/contractor-signup-options',
            ],
        ]);
    }

    /**
     * Dashboard widget — Recent Contractor Requests.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 5), 1), 20);
        $pendingStatuses = [
            SupervisorRegistration::STATUS_PENDING,
            SupervisorRegistration::STATUS_DOCUMENTS_REQUESTED,
        ];

        $totalPending = SupervisorRegistration::query()
            ->whereIn('status', $pendingStatuses)
            ->count();

        $items = SupervisorRegistration::query()
            ->with(['user:id,status'])
            ->whereIn('status', $pendingStatuses)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (SupervisorRegistration $r) => array_merge($r->toAdminListArray(), [
                'view' => [
                    'endpoint' => '/api/admin/supervisor-registrations/'.$r->id,
                ],
            ]))
            ->values()
            ->all();

        return ApiResponse::success('Recent contractor requests retrieved.', [
            'items' => $items,
            'total_pending' => $totalPending,
            'has_more' => $totalPending > count($items),
            'view_all' => [
                'endpoint' => '/api/admin/supervisor-registrations',
                'query' => ['status' => 'pending'],
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);

        return ApiResponse::success('Contractor application retrieved.', $row->toAdminDetailArray());
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'assigned_zone_ids' => ['nullable', 'array'],
            'assigned_zone_ids.*' => ['integer', 'exists:areas,id'],
        ]);

        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);
        $row = $this->registration->approve(
            $row,
            $request->user(),
            $data['assigned_zone_ids'] ?? null
        );

        return ApiResponse::success('Contractor approved.', $row->toAdminDetailArray());
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);
        $row = $this->registration->reject($row, $request->user(), $data['reason'], $data['notes'] ?? null);

        return ApiResponse::success('Contractor rejected.', $row->toAdminDetailArray());
    }

    public function requestDocuments(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);
        $row = $this->registration->requestDocuments(
            $row,
            $request->user(),
            $data['message'],
            $data['notes'] ?? null
        );

        return ApiResponse::success('Missing documents requested.', $row->toAdminDetailArray());
    }

    /**
     * Change approved contractor account status via URL only (no body).
     * POST .../account-status/{action}  action = suspend|activate|inactive
     */
    public function accountStatus(Request $request, int $id, string $action): JsonResponse
    {
        $action = strtolower(trim($action));
        if (! in_array($action, ['suspend', 'activate', 'inactive'], true)) {
            return ApiResponse::error('Invalid action. Use suspend, activate, or inactive in the URL.', 422);
        }

        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);
        $row = match ($action) {
            'suspend' => $this->registration->suspend($row, $request->user()),
            'inactive' => $this->registration->deactivate($row, $request->user()),
            default => $this->registration->activate($row, $request->user()),
        };

        $message = match ($action) {
            'suspend' => 'Contractor suspended.',
            'inactive' => 'Contractor set to inactive.',
            default => 'Contractor activated.',
        };

        return ApiResponse::success($message, $row->toAdminDetailArray());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);
        $result = $this->registration->permanentlyDelete($row);

        return ApiResponse::success('Contractor deleted.', $result);
    }

    /**
     * @return array{pending: int, active: int, inactive: int, suspended: int, rejected: int, all: int}
     */
    private function statusCounts(): array
    {
        return Cache::remember(SupervisorRegistrationService::STATUS_COUNTS_CACHE_KEY, 20, function () {
            $rows = SupervisorRegistration::query()
                ->leftJoin('users', 'users.id', '=', 'supervisor_registrations.user_id')
                ->select([
                    'supervisor_registrations.status as reg_status',
                    'users.status as user_status',
                    DB::raw('COUNT(*) as aggregate'),
                ])
                ->groupBy('supervisor_registrations.status', 'users.status')
                ->get();

            $counts = [
                'pending' => 0,
                'active' => 0,
                'inactive' => 0,
                'suspended' => 0,
                'rejected' => 0,
                'all' => 0,
            ];

            foreach ($rows as $row) {
                $n = (int) $row->aggregate;
                $counts['all'] += $n;
                $ui = $this->mapToUiStatus((string) $row->reg_status, $row->user_status);
                $counts[$ui] = ($counts[$ui] ?? 0) + $n;
            }

            return $counts;
        });
    }

    private function mapToUiStatus(string $regStatus, mixed $userStatus): string
    {
        if ($regStatus === SupervisorRegistration::STATUS_REJECTED) {
            return SupervisorRegistration::UI_REJECTED;
        }
        if (in_array($regStatus, [
            SupervisorRegistration::STATUS_PENDING,
            SupervisorRegistration::STATUS_DOCUMENTS_REQUESTED,
        ], true)) {
            return SupervisorRegistration::UI_PENDING;
        }

        $userStatus = strtolower((string) ($userStatus ?? 'active'));

        return match ($userStatus) {
            'suspended' => SupervisorRegistration::UI_SUSPENDED,
            'inactive' => SupervisorRegistration::UI_INACTIVE,
            default => SupervisorRegistration::UI_ACTIVE,
        };
    }

    private function applyUiStatusFilter($query, mixed $status): void
    {
        $status = strtolower(trim((string) ($status ?? '')));
        if ($status === '' || $status === 'all') {
            return;
        }

        // Raw registration statuses still supported.
        if (in_array($status, [
            SupervisorRegistration::STATUS_PENDING,
            SupervisorRegistration::STATUS_DOCUMENTS_REQUESTED,
            SupervisorRegistration::STATUS_APPROVED,
            SupervisorRegistration::STATUS_REJECTED,
        ], true) && ! in_array($status, [
            SupervisorRegistration::UI_ACTIVE,
            SupervisorRegistration::UI_INACTIVE,
            SupervisorRegistration::UI_SUSPENDED,
        ], true)) {
            if ($status === SupervisorRegistration::STATUS_PENDING) {
                $query->whereIn('status', [
                    SupervisorRegistration::STATUS_PENDING,
                    SupervisorRegistration::STATUS_DOCUMENTS_REQUESTED,
                ]);

                return;
            }
            $query->where('status', $status);

            return;
        }

        match ($status) {
            SupervisorRegistration::UI_PENDING => $query->whereIn('status', [
                SupervisorRegistration::STATUS_PENDING,
                SupervisorRegistration::STATUS_DOCUMENTS_REQUESTED,
            ]),
            SupervisorRegistration::UI_REJECTED => $query->where('status', SupervisorRegistration::STATUS_REJECTED),
            SupervisorRegistration::UI_ACTIVE => $query->where('status', SupervisorRegistration::STATUS_APPROVED)
                ->whereHas('user', fn ($q) => $q->where('status', 'active')),
            SupervisorRegistration::UI_INACTIVE => $query->where('status', SupervisorRegistration::STATUS_APPROVED)
                ->whereHas('user', fn ($q) => $q->where('status', 'inactive')),
            SupervisorRegistration::UI_SUSPENDED => $query->where('status', SupervisorRegistration::STATUS_APPROVED)
                ->whereHas('user', fn ($q) => $q->where('status', 'suspended')),
            default => null,
        };
    }

    private function applySearch($query, mixed $search): void
    {
        $search = trim((string) ($search ?? ''));
        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
