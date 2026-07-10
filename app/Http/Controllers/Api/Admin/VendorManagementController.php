<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\VendorStatus;
use App\Enums\VendorDocumentType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Services\Vendor\AdminVendorMetricsService;
use App\Services\Vendor\AdminVendorMobileService;
use App\Services\Vendor\AdminVendorProductService;
use App\Services\Vendor\VendorApplicationService;
use App\Services\Vendor\VendorApprovalService;
use App\Services\Vendor\VendorDashboardService;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorManagementController extends Controller
{
    public function __construct(
        private readonly VendorApprovalService $approval,
        private readonly VendorRegistrationService $registration,
        private readonly VendorApplicationService $application,
        private readonly VendorDashboardService $dashboard,
        private readonly AdminVendorMetricsService $metrics,
        private readonly AdminVendorMobileService $mobile,
        private readonly AdminVendorProductService $adminProducts
    ) {}

    /**
     * Admin mobile app — Vendor Management screen (summary + searchable vendor list).
     */
    public function management(Request $request): JsonResponse
    {
        return ApiResponse::success('Vendor management retrieved.', $this->mobile->managementIndex($request));
    }

    /**
     * Admin mobile app — vendor detail with products and enable/disable toggle metadata.
     */
    public function managementDetail(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);

        return ApiResponse::success('Vendor management detail retrieved.', $this->mobile->managementDetail($vendor, $request));
    }

    /**
     * Admin mobile app — toggle vendor product enabled/disabled on marketplace.
     */
    public function toggleProduct(Request $request, int $vendorId, int $productId): JsonResponse
    {
        $vendor = Vendor::findOrFail($vendorId);
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->findOrFail($productId);

        $updated = $this->adminProducts->toggle($vendorProduct, $request->user());

        return ApiResponse::success(
            $updated->disabled_by_admin ? 'Product disabled on marketplace.' : 'Product enabled on marketplace.',
            [
                'product' => $this->mobile->formatProductItem($vendor, $updated),
            ]
        );
    }

    public function index(Request $request): JsonResponse
    {
        $sort = $request->query('sort', 'newest');
        $q = Vendor::with(['profile', 'user'])
            ->when($request->query('status'), function ($query, $status) {
                if (str_contains((string) $status, ',')) {
                    $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $status))));

                    return $query->whereIn('status', $statuses);
                }

                return $query->where('status', $status);
            })
            ->when($request->query('search'), function ($query, $search) {
                $query->whereHas('profile', function ($pq) use ($search) {
                    $pq->where('business_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%");
                });
            })
            ->when($sort === 'oldest', fn ($query) => $query->oldest())
            ->when($sort === 'business', fn ($query) => $query->leftJoin('vendor_profiles', 'vendors.id', '=', 'vendor_profiles.vendor_id')->orderBy('vendor_profiles.business_name')->select('vendors.*'))
            ->when(! in_array($sort, ['oldest', 'business'], true), fn ($query) => $query->latest());

        $paginator = $q->paginate(min((int) $request->query('per_page', 15), 100));
        $vendorIds = collect($paginator->items())->pluck('id')->all();
        $metricsMap = $this->metrics->mapForVendorIds($vendorIds);

        return ApiResponse::success('Vendors retrieved.', [
            'items' => collect($paginator->items())
                ->map(fn (Vendor $vendor) => $this->metrics->formatListItem($vendor, $metricsMap[$vendor->id] ?? null))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Mobile admin home — "Recent Vendor Requests" widget (pending + under_review).
     */
    public function recentRequests(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 5), 1), 20);
        $pendingStatuses = [VendorStatus::Pending->value, VendorStatus::UnderReview->value];

        $totalPending = Vendor::whereIn('status', $pendingStatuses)->count();

        $vendors = Vendor::with(['profile', 'user', 'documents', 'categories'])
            ->whereIn('status', $pendingStatuses)
            ->latest()
            ->limit($limit)
            ->get();

        $items = $vendors->map(fn (Vendor $vendor) => $this->toRecentRequestCard($vendor))->values()->all();

        return ApiResponse::success('Recent vendor requests retrieved.', [
            'items' => $items,
            'total_pending' => $totalPending,
            'has_more' => $totalPending > count($items),
            'view_all' => [
                'endpoint' => '/api/admin/vendors',
                'query' => [
                    'status' => implode(',', $pendingStatuses),
                    'sort' => 'newest',
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRecentRequestCard(Vendor $vendor): array
    {
        $vendor->loadMissing(['profile', 'user', 'documents', 'categories', 'approvalLogs.performer']);
        $profile = $vendor->profile;
        $application = $this->application->applicationPayload($vendor);

        $submittedAt = $vendor->approvalLogs
            ->firstWhere('action', 'submitted_for_review')
            ?->created_at
            ?? $profile?->onboarding_completed_at
            ?? $vendor->created_at;

        return [
            'vendor_id' => $vendor->id,
            'business_name' => $profile?->business_name,
            'owner_name' => $profile?->owner_name,
            'email' => $profile?->email ?? $vendor->user?->email,
            'phone' => $profile?->phone ?? $vendor->user?->phone,
            'logo_url' => $profile?->logo_url,
            'status' => $vendor->status,
            'status_label' => $vendor->statusEnum()->label(),
            'display_status' => $vendor->statusEnum()->displayStatus(),
            'completion_percent' => $application['completion_percent'] ?? 0,
            'submitted_at' => $submittedAt?->toIso8601String(),
            'submitted_at_formatted' => $submittedAt?->format('j M Y \a\t g:i A'),
            'created_at' => $vendor->created_at?->toIso8601String(),
            'contact' => $this->buildContactSection($vendor),
            'business_details' => $this->buildBusinessDetailsSection($vendor),
            'bank_details' => $this->buildBankDetailsSection($vendor),
            'documents' => $this->buildDocumentsSection($vendor),
            'application' => [
                'completion_percent' => $application['completion_percent'] ?? 0,
                'profile_complete' => $application['profile_complete'] ?? false,
                'documents_complete' => $application['documents_complete'] ?? false,
                'categories_complete' => $application['categories_complete'] ?? false,
                'terms_accepted' => $application['terms_accepted'] ?? false,
                'onboarding_complete' => $application['onboarding_complete'] ?? false,
                'missing_profile_fields' => $application['missing_profile_fields'] ?? [],
                'required_documents' => $application['required_documents'] ?? [],
            ],
            'actions' => [
                'approve' => [
                    'method' => 'POST',
                    'endpoint' => "/api/admin/vendors/{$vendor->id}/approve",
                ],
                'reject' => [
                    'method' => 'POST',
                    'endpoint' => "/api/admin/vendors/{$vendor->id}/reject",
                ],
                'detail' => [
                    'method' => 'GET',
                    'endpoint' => "/api/admin/vendors/{$vendor->id}/application-detail",
                ],
            ],
        ];
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success('Vendor statistics.', [
            'total' => Vendor::count(),
            'pending' => Vendor::where('status', VendorStatus::Pending->value)->count(),
            'approved' => Vendor::where('status', VendorStatus::Approved->value)->count(),
            'rejected' => Vendor::where('status', VendorStatus::Rejected->value)->count(),
            'suspended' => Vendor::where('status', VendorStatus::Suspended->value)->count(),
            'under_review' => Vendor::where('status', VendorStatus::UnderReview->value)->count(),
            'disabled' => Vendor::where('status', VendorStatus::Disabled->value)->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $vendor = Vendor::with(['profile', 'user', 'approvalLogs.performer', 'documents', 'categories'])->findOrFail($id);

        return ApiResponse::success('Vendor retrieved.', [
            'detail' => $this->buildApplicationDetail($vendor),
            'vendor' => $vendor,
            'application' => $this->application->applicationPayload($vendor),
            'metrics' => $this->metrics->forVendor($vendor),
            'statistics' => $this->dashboard->stats($vendor),
            'analytics' => $this->dashboard->analytics($vendor),
        ]);
    }

    /**
     * Mobile admin — full vendor application screen (Contact, Business details, Documents, Approve/Reject).
     */
    public function applicationDetail(int $id): JsonResponse
    {
        $vendor = Vendor::with(['profile', 'user', 'approvalLogs.performer', 'documents', 'categories'])->findOrFail($id);

        return ApiResponse::success('Vendor application retrieved.', $this->buildApplicationDetail($vendor));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApplicationDetail(Vendor $vendor): array
    {
        $vendor->loadMissing(['profile', 'user', 'documents', 'categories', 'approvalLogs.performer']);
        $profile = $vendor->profile;
        $application = $this->application->applicationPayload($vendor);

        $submittedAt = $vendor->approvalLogs
            ->firstWhere('action', 'submitted_for_review')
            ?->created_at
            ?? $profile?->onboarding_completed_at
            ?? $vendor->created_at;

        $pendingStatuses = [VendorStatus::Pending->value, VendorStatus::UnderReview->value];
        $canReview = in_array($vendor->status, $pendingStatuses, true);

        return [
            'vendor_id' => $vendor->id,
            'title' => 'Vendor application',
            'summary' => [
                'business_name' => $profile?->business_name,
                'owner_name' => $profile?->owner_name,
                'logo_url' => $profile?->logo_url,
                'status' => $vendor->status,
                'status_label' => $vendor->statusEnum()->label(),
                'display_status' => $vendor->statusEnum()->displayStatus(),
                'rejection_reason' => $vendor->rejection_reason,
                'submitted_at' => $submittedAt?->toIso8601String(),
                'submitted_at_formatted' => $submittedAt?->format('j M Y \a\t g:i A'),
                'completion_percent' => $application['completion_percent'] ?? 0,
            ],
            'contact' => $this->buildContactSection($vendor),
            'business_details' => $this->buildBusinessDetailsSection($vendor),
            'bank_details' => $this->buildBankDetailsSection($vendor),
            'documents' => $this->buildDocumentsSection($vendor),
            'application' => $application,
            'approval_logs' => $application['approval_logs'] ?? [],
            'actions' => [
                'can_approve' => $canReview,
                'can_reject' => $canReview,
                'approve' => [
                    'method' => 'POST',
                    'endpoint' => "/api/admin/vendors/{$vendor->id}/approve",
                ],
                'reject' => [
                    'method' => 'POST',
                    'endpoint' => "/api/admin/vendors/{$vendor->id}/reject",
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactSection(Vendor $vendor): array
    {
        $profile = $vendor->profile;

        return [
            'email' => $profile?->email ?? $vendor->user?->email,
            'phone' => $profile?->phone ?? $vendor->user?->phone,
            'authorized_person_name' => $profile?->owner_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBusinessDetailsSection(Vendor $vendor): array
    {
        $profile = $vendor->profile;

        return [
            'vendor_type' => $profile?->vendor_type,
            'vendor_type_label' => $profile?->vendor_type_label,
            'trade_license_number' => $profile?->trade_license_number,
            'tax_vat_number' => $profile?->tax_vat_number,
            'emirate' => $profile?->emirate,
            'city' => $profile?->city,
            'address' => $profile?->address,
            'google_maps_location' => $profile?->google_maps_location,
            'delivery_radius' => $profile?->delivery_radius,
            'operating_hours' => $profile?->operating_hours,
            'minimum_order_amount' => $profile?->minimum_order_amount,
            'years_in_business' => $profile?->years_in_business,
            'description' => $profile?->description,
            'terms_accepted_at' => $profile?->terms_accepted_at?->toIso8601String(),
            'categories' => $vendor->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
            ])->values()->all(),
            'category_ids' => $vendor->categories->pluck('id')->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBankDetailsSection(Vendor $vendor): array
    {
        $profile = $vendor->profile;

        return [
            'bank_name' => $profile?->bank_name,
            'iban' => $profile?->iban,
            'account_holder_name' => $profile?->account_holder_name,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDocumentsSection(Vendor $vendor): array
    {
        return $vendor->documents->map(fn ($doc) => [
            'id' => $doc->id,
            'type' => $doc->type,
            'label' => VendorDocumentType::tryFrom($doc->type)?->label() ?? ucfirst(str_replace('_', ' ', $doc->type)),
            'original_name' => $doc->original_name,
            'file_url' => $doc->file_url,
            'verification_status' => $doc->verification_status,
            'admin_notes' => $doc->admin_notes,
            'verified_at' => $doc->verified_at?->toIso8601String(),
        ])->values()->all();
    }

    public function analytics(int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);

        return ApiResponse::success('Vendor analytics.', [
            'statistics' => $this->dashboard->stats($vendor),
            'analytics' => $this->dashboard->analytics($vendor),
        ]);
    }

    public function products(Request $request, int $id): JsonResponse
    {
        Vendor::findOrFail($id);

        $paginator = VendorProduct::with(['product.category', 'inventory', 'currentPrice'])
            ->where('vendor_id', $id)
            ->when($request->query('approval_status'), fn ($q, $status) => $q->where('approval_status', $status))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ApiResponse::success('Vendor products retrieved.', [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function orders(Request $request, int $id): JsonResponse
    {
        Vendor::findOrFail($id);

        $paginator = VendorOrderMapping::with(['order.user', 'vendor.profile'])
            ->where('vendor_id', $id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('id', 'like', "%{$search}%")
                        ->orWhere('order_id', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ApiResponse::success('Vendor orders retrieved.', [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::with('profile')->findOrFail($id);
        $data = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'owner_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:32',
            'trade_license_number' => 'nullable|string|max:100',
            'vendor_type' => ['nullable', \Illuminate\Validation\Rule::in(\App\Enums\VendorType::values())],
            'emirate' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:2000',
            'google_maps_location' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:191',
            'iban' => 'nullable|string|max:64',
            'account_holder_name' => 'nullable|string|max:191',
            'delivery_radius' => 'nullable|numeric|min:0|max:10000',
            'operating_hours' => 'nullable|string|max:500',
            'minimum_order_amount' => 'nullable|numeric|min:0|max:1000000',
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

        return ApiResponse::success('Vendor approved.', [
            'vendor' => $vendor,
            'detail' => $this->buildApplicationDetail($vendor),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:500',
        ]);
        $vendor = $this->approval->reject($vendor, $request->user(), $data['reason'], $data['notes'] ?? null);

        return ApiResponse::success('Vendor rejected.', [
            'vendor' => $vendor,
            'detail' => $this->buildApplicationDetail($vendor),
        ]);
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

    public function underReview(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $vendor = $this->approval->underReview($vendor, $request->user(), $data['notes'] ?? null);

        return ApiResponse::success('Vendor marked under review.', ['vendor' => $vendor]);
    }

    public function disable(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $vendor = $this->approval->disable($vendor, $request->user(), $data['notes'] ?? null);

        return ApiResponse::success('Vendor disabled.', ['vendor' => $vendor]);
    }

    /**
     * Permanently delete vendor account and linked marketplace data.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $vendor = Vendor::findOrFail($id);
        $this->approval->permanentlyDelete($vendor, $request->user(), $data['notes'] ?? null);

        return ApiResponse::success('Vendor permanently deleted.', [
            'vendor_id' => $id,
            'deleted' => true,
        ]);
    }
}
