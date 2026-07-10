<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminVendorBulkActionRequest;
use App\Models\Vendor;
use App\Services\Vendor\AdminVendorExportService;
use App\Services\Vendor\AdminVendorListService;
use App\Services\Vendor\VendorApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorListController extends Controller
{
    public function __construct(
        private readonly AdminVendorListService $list,
        private readonly AdminVendorExportService $export,
        private readonly VendorApprovalService $approval
    ) {
        $this->middleware('role:admin');
    }

    public function index(Request $request): View
    {
        return $this->renderList($request, null, 'All Vendors', 'Each row is one vendor — metrics in the performance column belong to that vendor only.');
    }

    public function pending(Request $request): View
    {
        return $this->renderList($request, 'pending', 'Pending Approvals', 'Review new vendor applications awaiting approval.');
    }

    public function active(Request $request): View
    {
        return $this->renderList($request, 'active', 'Active Vendors', 'Approved vendors currently operating on the marketplace.');
    }

    public function suspended(Request $request): View
    {
        return $this->renderList($request, 'suspended', 'Suspended Vendors', 'Suspended or disabled vendor accounts.');
    }

    public function export(Request $request)
    {
        $preset = $request->query('preset');
        $vendors = $this->list->exportCollection($request, $preset);

        return $this->export->streamCsv($vendors);
    }

    public function bulk(AdminVendorBulkActionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $vendors = Vendor::whereIn('id', $data['vendor_ids'])->get();
        $count = 0;

        foreach ($vendors as $vendor) {
            match ($data['action']) {
                'approve' => $this->approval->approve($vendor, $request->user(), $data['notes'] ?? null),
                'suspend' => $this->approval->suspend($vendor, $request->user(), $data['notes'] ?? null),
                'activate' => $this->approval->activate($vendor, $request->user(), $data['notes'] ?? null),
                'disable' => $this->approval->disable($vendor, $request->user(), $data['notes'] ?? null),
                'delete' => $this->approval->permanentlyDelete($vendor, $request->user(), $data['notes'] ?? null),
                default => null,
            };
            $count++;
        }

        return back()->with('success', "Bulk action applied to {$count} vendor(s).");
    }

    private function renderList(Request $request, ?string $preset, string $title, string $subtitle): View
    {
        $vendors = $this->list->paginate($request, $preset);
        $metricsMap = $this->list->metricsForIds($vendors->getCollection()->pluck('id')->all());
        $stats = $this->list->statusCounts();

        $recentRequests = Vendor::with(['profile', 'user'])
            ->whereIn('status', [VendorStatus::Pending->value, VendorStatus::UnderReview->value])
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.vendors.index', [
            'vendors' => $vendors,
            'stats' => $stats,
            'metricsMap' => $metricsMap,
            'sort' => $request->query('sort', 'newest'),
            'recentRequests' => $recentRequests,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle,
            'listPreset' => $preset,
            'listService' => $this->list,
        ]);
    }
}
