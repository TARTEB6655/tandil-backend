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
        $excludeDemo = $request->boolean('exclude_demo');
        $demoMarker = \Database\Seeders\VendorDemoOrdersSeeder::DEMO_MARKER;

        $paginator = VendorOrderMapping::with(['order.user', 'vendor.profile', 'order.items.product'])
            ->when($request->query('vendor_id'), fn ($q, $vendorId) => $q->where('vendor_id', $vendorId))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('payment_status'), function ($q, $status) {
                $q->whereHas('order', fn ($oq) => $oq->where('payment_status', $status));
            })
            ->when($excludeDemo, function ($q) use ($demoMarker) {
                $q->whereHas('order', function ($oq) use ($demoMarker) {
                    $oq->where(function ($inner) use ($demoMarker) {
                        $inner->whereNull('special_instructions')
                            ->orWhere('special_instructions', 'not like', $demoMarker.'%');
                    });
                });
            })
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('id', 'like', "%{$search}%")
                        ->orWhere('order_id', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        $items = collect($paginator->items())->map(function (VendorOrderMapping $mapping) use ($demoMarker) {
            $order = $mapping->order;
            $instructions = (string) ($order?->special_instructions ?? '');
            $isDemo = str_starts_with($instructions, $demoMarker);
            $vendorProducts = $order?->items
                ?->filter(fn ($item) => (int) ($item->product?->vendor_id ?? 0) === (int) $mapping->vendor_id)
                ->values() ?? collect();

            return [
                'id' => $mapping->id,
                'order_id' => $mapping->order_id,
                'vendor_id' => $mapping->vendor_id,
                'vendor_name' => $mapping->vendor?->profile?->business_name,
                'status' => $mapping->status,
                'is_demo' => $isDemo,
                'subtotal' => (float) $mapping->subtotal,
                'tax_amount' => (float) $mapping->tax_amount,
                'shipping_amount' => (float) $mapping->shipping_amount,
                'total_amount' => (float) $mapping->total_amount,
                'commission_amount' => (float) ($mapping->commission_amount ?? 0),
                'tracking_number' => $mapping->tracking_number,
                'payment_status' => $order?->payment_status,
                'payment_method' => $order?->payment_method,
                'customer_name' => $order?->user?->name ?? $order?->guest_full_name,
                'customer_email' => $order?->user?->email ?? $order?->guest_email,
                'products' => $vendorProducts->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                ])->values()->all(),
                'created_at' => $mapping->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return ApiResponse::success('Vendor orders.', [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
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
