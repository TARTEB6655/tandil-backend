<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorDocument;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Services\Vendor\AdminMarketplaceAnalyticsService;
use App\Services\Vendor\AdminVendorOrderService;
use App\Services\Vendor\AdminVendorProductService;
use App\Services\Vendor\VendorApprovalService;
use App\Services\Vendor\VendorDocumentService;
use App\Support\MarketplaceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceAdminController extends Controller
{
    public function __construct(
        private readonly AdminMarketplaceAnalyticsService $analytics,
        private readonly AdminVendorProductService $products,
        private readonly AdminVendorOrderService $orders,
        private readonly VendorApprovalService $approval,
        private readonly VendorDocumentService $documents
    ) {}

    public function analytics(): JsonResponse
    {
        return ApiResponse::success('Marketplace analytics.', [
            'overview' => $this->analytics->overview(),
            'top_vendors' => $this->analytics->topVendorsByRevenue(10),
        ]);
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success('Marketplace settings.', [
            'commission_percent' => MarketplaceSettings::commissionPercent(),
            'product_approval_required' => MarketplaceSettings::productApprovalRequired(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commission_percent' => 'required|numeric|min:0|max:100',
            'product_approval_required' => 'required|boolean',
        ]);

        MarketplaceSettings::setCommissionPercent((float) $data['commission_percent']);
        MarketplaceSettings::setProductApprovalRequired((bool) $data['product_approval_required']);

        return ApiResponse::success('Settings updated.', $this->analytics->overview()['settings']);
    }

    public function products(Request $request): JsonResponse
    {
        $items = VendorProduct::with(['product.category', 'vendor.profile', 'inventory'])
            ->when($request->query('vendor_id'), fn ($q, $vendorId) => $q->where('vendor_id', $vendorId))
            ->when($request->query('approval_status'), fn ($q, $s) => $q->where('approval_status', $s))
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ApiResponse::success('Vendor products.', ['items' => $items]);
    }

    public function approveProduct(Request $request, int $id): JsonResponse
    {
        $vp = VendorProduct::findOrFail($id);
        $vp = $this->products->approve($vp, $request->user(), $request->input('notes'));

        return ApiResponse::success('Product approved.', ['vendor_product' => $vp]);
    }

    public function rejectProduct(Request $request, int $id): JsonResponse
    {
        $vp = VendorProduct::findOrFail($id);
        $request->validate(['reason' => 'required|string|max:1000']);
        $vp = $this->products->reject($vp, $request->user(), $request->input('reason'));

        return ApiResponse::success('Product rejected.', ['vendor_product' => $vp]);
    }

    public function vendorOrders(Request $request): JsonResponse
    {
        $items = VendorOrderMapping::with(['order.user', 'vendor.profile'])
            ->when($request->query('vendor_id'), fn ($q, $vendorId) => $q->where('vendor_id', $vendorId))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('id', 'like', "%{$search}%")
                        ->orWhere('order_id', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ApiResponse::success('Vendor orders.', ['items' => $items]);
    }

    public function verifyDocument(Request $request, int $id): JsonResponse
    {
        $doc = VendorDocument::findOrFail($id);
        $data = $request->validate([
            'verification_status' => 'required|in:verified,rejected',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $doc = $this->documents->verify($doc, $request->user(), $data['verification_status'], $data['admin_notes'] ?? null);

        return ApiResponse::success('Document updated.', ['document' => $doc]);
    }

    public function destroyVendor(Request $request, int $id): JsonResponse
    {
        return app(\App\Http\Controllers\Api\Admin\VendorManagementController::class)->destroy($request, $id);
    }
}
