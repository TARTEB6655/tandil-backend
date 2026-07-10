<?php

namespace App\Services\Vendor;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminVendorListService
{
    public function __construct(
        private readonly AdminVendorMetricsService $metrics
    ) {}

    /**
     * @return array{total: int, pending: int, under_review: int, approved: int, suspended: int, rejected: int, disabled: int}
     */
    public function statusCounts(): array
    {
        return [
            'total' => Vendor::count(),
            'pending' => Vendor::where('status', VendorStatus::Pending->value)->count(),
            'under_review' => Vendor::where('status', VendorStatus::UnderReview->value)->count(),
            'approved' => Vendor::where('status', VendorStatus::Approved->value)->count(),
            'suspended' => Vendor::where('status', VendorStatus::Suspended->value)->count(),
            'rejected' => Vendor::where('status', VendorStatus::Rejected->value)->count(),
            'disabled' => Vendor::where('status', VendorStatus::Disabled->value)->count(),
        ];
    }

    public function paginate(Request $request, ?string $preset = null): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->query('per_page', 15), 5), 100);

        return $this->query($request, $preset)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Builder<Vendor>
     */
    public function query(Request $request, ?string $preset = null): Builder
    {
        $sort = $request->query('sort', 'newest');
        $verified = $request->query('verified');

        return Vendor::query()
            ->with(['profile', 'user', 'documents'])
            ->when($preset === 'pending', fn ($q) => $q->whereIn('status', [
                VendorStatus::Pending->value,
                VendorStatus::UnderReview->value,
            ]))
            ->when($preset === 'active', fn ($q) => $q->where('status', VendorStatus::Approved->value))
            ->when($preset === 'suspended', fn ($q) => $q->whereIn('status', [
                VendorStatus::Suspended->value,
                VendorStatus::Disabled->value,
            ]))
            ->when(! $preset && $request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->whereHas('profile', function ($pq) use ($search) {
                        $pq->where('business_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('owner_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                });
            })
            ->when($verified === 'yes', function ($q) {
                $q->whereHas('documents')
                    ->whereDoesntHave('documents', fn ($dq) => $dq->where('verification_status', '!=', 'verified'));
            })
            ->when($verified === 'no', function ($q) {
                $q->where(function ($query) {
                    $query->whereDoesntHave('documents')
                        ->orWhereHas('documents', fn ($dq) => $dq->whereIn('verification_status', ['pending', 'rejected']));
                });
            })
            ->when($sort === 'oldest', fn ($q) => $q->oldest())
            ->when($sort === 'business', fn ($q) => $q
                ->leftJoin('vendor_profiles', 'vendors.id', '=', 'vendor_profiles.vendor_id')
                ->orderBy('vendor_profiles.business_name')
                ->select('vendors.*'))
            ->when($sort === 'revenue', function ($q) {
                $q->leftJoin('vendor_order_mappings', 'vendors.id', '=', 'vendor_order_mappings.vendor_id')
                    ->selectRaw('vendors.*, COALESCE(SUM(CASE WHEN vendor_order_mappings.status != ? THEN vendor_order_mappings.total_amount ELSE 0 END), 0) as sort_revenue', ['cancelled'])
                    ->groupBy('vendors.id')
                    ->orderByDesc('sort_revenue');
            })
            ->when(! in_array($sort, ['oldest', 'business', 'revenue'], true), fn ($q) => $q->latest());
    }

    /**
     * @param  list<int>  $vendorIds
     * @return array<int, array<string, mixed>>
     */
    public function metricsForIds(array $vendorIds): array
    {
        return $this->metrics->mapForVendorIds($vendorIds);
    }

    public function isVerified(Vendor $vendor): bool
    {
        $docs = $vendor->documents;
        if ($docs->isEmpty()) {
            return false;
        }

        return $docs->every(fn ($doc) => $doc->verification_status === 'verified');
    }

    /**
     * @return list<Vendor>
     */
    public function exportCollection(Request $request, ?string $preset = null): array
    {
        return $this->query($request, $preset)->get()->all();
    }
}
