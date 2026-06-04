<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\VendorStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\Vendor\VendorApprovalService;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorManagementController extends Controller
{
    public function __construct(
        private readonly VendorApprovalService $approval,
        private readonly VendorRegistrationService $registration
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = Vendor::with(['profile', 'user'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('search'), function ($query, $search) {
                $query->whereHas('profile', function ($pq) use ($search) {
                    $pq->where('business_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%");
                });
            })
            ->latest();

        $paginator = $q->paginate(min((int) $request->query('per_page', 15), 100));

        return ApiResponse::success('Vendors retrieved.', [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success('Vendor statistics.', [
            'total' => Vendor::count(),
            'pending' => Vendor::where('status', VendorStatus::Pending->value)->count(),
            'approved' => Vendor::where('status', VendorStatus::Approved->value)->count(),
            'rejected' => Vendor::where('status', VendorStatus::Rejected->value)->count(),
            'suspended' => Vendor::where('status', VendorStatus::Suspended->value)->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $vendor = Vendor::with(['profile', 'user', 'approvalLogs.performer'])->findOrFail($id);

        return ApiResponse::success('Vendor retrieved.', ['vendor' => $vendor]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::with('profile')->findOrFail($id);
        $data = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'owner_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:2000',
            'tax_vat_number' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:5000',
            'logo' => 'nullable|image|max:5120',
            'logo_remove' => 'nullable|boolean',
        ]);

        $vendor = $this->registration->updateProfile($vendor, $data, $request->file('logo'), $request->boolean('logo_remove'));

        return ApiResponse::success('Vendor updated.', ['vendor' => $vendor]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $vendor = $this->approval->approve($vendor, $request->user(), $data['notes'] ?? null);

        return ApiResponse::success('Vendor approved.', ['vendor' => $vendor]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:500',
        ]);
        $vendor = $this->approval->reject($vendor, $request->user(), $data['reason'], $data['notes'] ?? null);

        return ApiResponse::success('Vendor rejected.', ['vendor' => $vendor]);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $vendor = $this->approval->suspend($vendor, $request->user(), $data['notes'] ?? null);

        return ApiResponse::success('Vendor suspended.', ['vendor' => $vendor]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $vendor = $this->approval->activate($vendor, $request->user(), $data['notes'] ?? null);

        return ApiResponse::success('Vendor activated.', ['vendor' => $vendor]);
    }
}
