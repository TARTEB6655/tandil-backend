<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SupervisorRegistration;
use App\Services\Supervisor\SupervisorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorRegistrationAdminController extends Controller
{
    public function __construct(
        private readonly SupervisorRegistrationService $registration
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupervisorRegistration::query()->with(['documents', 'user'])->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $page = $query->paginate($perPage);

        return ApiResponse::success('Supervisor registrations retrieved.', [
            'items' => collect($page->items())->map(fn (SupervisorRegistration $r) => $r->toApiArray())->values(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);

        return ApiResponse::success('Supervisor registration retrieved.', $row->toApiArray());
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'employee_id' => ['nullable', 'string', 'max:64'],
            'assigned_zone_ids' => ['nullable', 'array'],
            'assigned_zone_ids.*' => ['integer', 'exists:areas,id'],
        ]);

        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);
        $row = $this->registration->approve(
            $row,
            $request->user(),
            $data['notes'] ?? null,
            $data['employee_id'] ?? null,
            $data['assigned_zone_ids'] ?? null
        );

        return ApiResponse::success('Supervisor registration approved.', $row->toApiArray());
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = SupervisorRegistration::query()->with(['documents', 'user'])->findOrFail($id);
        $row = $this->registration->reject($row, $request->user(), $data['reason'], $data['notes'] ?? null);

        return ApiResponse::success('Supervisor registration rejected.', $row->toApiArray());
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

        return ApiResponse::success('Missing documents requested.', $row->toApiArray());
    }
}
