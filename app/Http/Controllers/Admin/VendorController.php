<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorDocumentType;
use App\Enums\VendorOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorBulkProductActionRequest;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorAnalyticsShare;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Notifications\AdminNotification;
use App\Services\Vendor\AdminVendorActivityService;
use App\Services\Vendor\AdminVendorListService;
use App\Services\Vendor\AdminVendorMetricsService;
use App\Services\Vendor\AdminVendorOrderListService;
use App\Services\Vendor\AdminVendorOrderService;
use App\Services\Vendor\AdminVendorProductListService;
use App\Services\Vendor\AdminVendorProductService;
use App\Services\Vendor\AdminVendorRevenueService;
use App\Services\Vendor\VendorApplicationService;
use App\Services\Vendor\VendorApprovalService;
use App\Services\Vendor\VendorDashboardService;
use App\Services\Vendor\VendorDocumentService;
use App\Services\Vendor\VendorPerformanceAnalyticsService;
use App\Services\Vendor\VendorRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\Vendor\VendorOrderService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorApprovalService $approval,
        private readonly VendorRegistrationService $registration,
        private readonly VendorDocumentService $documents,
        private readonly VendorApplicationService $application,
        private readonly VendorDashboardService $dashboard,
        private readonly VendorPerformanceAnalyticsService $performanceAnalytics,
        private readonly AdminVendorMetricsService $vendorMetrics,
        private readonly AdminVendorListService $listService,
        private readonly AdminVendorRevenueService $revenueService,
        private readonly AdminVendorActivityService $activityService,
        private readonly AdminVendorProductService $adminProducts,
        private readonly AdminVendorProductListService $productList,
        private readonly AdminVendorOrderListService $orderList,
        private readonly AdminVendorOrderService $adminOrders,
        private readonly VendorOrderService $vendorOrders,
    ) {
        $this->middleware('role:admin');
    }

    public function show(Vendor $vendor): View
    {
        $vendor->load([
            'profile',
            'user',
            'approvalLogs.performer',
            'vendorProducts.product',
            'documents.verifier',
            'categories',
        ]);

        $statistics = $this->dashboard->stats($vendor);
        $metrics = $this->vendorMetrics->forVendor($vendor);
        $analytics = $this->dashboard->analytics($vendor);
        $revenue = $this->revenueService->forVendor($vendor);

        return view('admin.vendors.show', [
            'vendor' => $vendor,
            'documentTypes' => VendorDocumentType::cases(),
            'application' => $this->application->applicationPayload($vendor),
            'statistics' => $statistics,
            'metrics' => $metrics,
            'analytics' => $analytics,
            'revenue' => $revenue,
            'isVerified' => $this->listService->isVerified($vendor),
            'recentProducts' => $vendor->vendorProducts()->with(['product', 'inventory'])->latest()->limit(6)->get(),
            'recentOrders' => $statistics['recent_orders'] ?? [],
        ]);
    }

    public function products(Request $request, Vendor $vendor): View
    {
        Gate::authorize('manageProducts', $vendor);
        $vendor->load('profile');

        $products = $this->productList->paginate($vendor, $request);
        $productIds = $products->getCollection()->pluck('product_id')->filter()->all();
        $salesMap = $this->productList->salesCounts($vendor->id, $productIds);

        return view('admin.vendors.products', [
            'vendor' => $vendor,
            'products' => $products,
            'stats' => $this->productList->stats($vendor),
            'categories' => $this->productList->categoriesForVendor($vendor),
            'salesMap' => $salesMap,
        ]);
    }

    public function showProduct(Vendor $vendor, VendorProduct $vendorProduct): View
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);

        $vendorProduct->load([
            'product.category',
            'product.images',
            'inventory',
            'currentPrice',
            'approvedByUser',
            'disabledByAdminUser',
        ]);

        $sales = $this->productList->salesCounts($vendor->id, [$vendorProduct->product_id]);

        return view('admin.vendors.products.show', [
            'vendor' => $vendor,
            'vendorProduct' => $vendorProduct,
            'totalSales' => $sales[$vendorProduct->product_id] ?? 0,
        ]);
    }

    public function bulkProducts(VendorBulkProductActionRequest $request, Vendor $vendor): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);

        $result = $this->adminProducts->bulk(
            $vendor->id,
            $request->input('product_ids'),
            $request->input('action'),
            $request->user(),
            $request->input('reason')
        );

        return back()->with('success', "Bulk action completed. {$result['processed']} updated".($result['failed'] ? ", {$result['failed']} skipped." : '.'));
    }

    public function orders(Request $request, Vendor $vendor): View
    {
        Gate::authorize('viewOrders', $vendor);
        $vendor->load('profile');

        return view('admin.vendors.orders', [
            'vendor' => $vendor,
            'orders' => $this->orderList->paginate($vendor, $request),
            'stats' => $this->orderList->stats($vendor),
        ]);
    }

    public function showOrder(Vendor $vendor, VendorOrderMapping $vendorOrder): View
    {
        Gate::authorize('viewOrders', $vendor);
        abort_unless($vendorOrder->vendor_id === $vendor->id, 404);

        $vendorOrder->load([
            'order.user',
            'order.items.product',
            'vendor.profile',
            'statusLogs.changedByUser',
            'cancelledByUser',
        ]);

        return view('admin.vendors.orders.show', [
            'vendor' => $vendor,
            'vendorOrder' => $vendorOrder,
        ]);
    }

    public function exportOrders(Request $request, Vendor $vendor)
    {
        Gate::authorize('viewOrders', $vendor);

        return $this->orderList->exportCsv($vendor, $request);
    }

    public function updateOrderStatus(Request $request, Vendor $vendor, VendorOrderMapping $vendorOrder): RedirectResponse
    {
        Gate::authorize('viewOrders', $vendor);
        abort_unless($vendorOrder->vendor_id === $vendor->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(VendorOrderStatus::values())],
            'note' => 'nullable|string|max:500',
        ]);

        $this->adminOrders->updateStatus(
            $vendorOrder,
            VendorOrderStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null
        );
        $this->adminOrders->recalculateCommission($vendorOrder->fresh());

        return back()->with('success', 'Order status updated.');
    }

    public function cancelOrder(Request $request, Vendor $vendor, VendorOrderMapping $vendorOrder): RedirectResponse
    {
        Gate::authorize('viewOrders', $vendor);
        abort_unless($vendorOrder->vendor_id === $vendor->id, 404);

        $request->validate(['reason' => 'required|string|max:1000']);
        $this->adminOrders->cancel($vendorOrder, $request->user(), $request->input('reason'));

        return back()->with('success', 'Order cancelled.');
    }

    public function updateOrderPayment(Request $request, Vendor $vendor, VendorOrderMapping $vendorOrder): RedirectResponse
    {
        Gate::authorize('viewOrders', $vendor);
        abort_unless($vendorOrder->vendor_id === $vendor->id, 404);

        $data = $request->validate([
            'payment_status' => 'required|in:pending,paid,failed,refunded',
        ]);

        $this->adminOrders->updatePaymentStatus($vendorOrder, $data['payment_status'], $request->user());

        return back()->with('success', 'Payment status updated.');
    }

    public function refundOrder(Request $request, Vendor $vendor, VendorOrderMapping $vendorOrder): RedirectResponse
    {
        Gate::authorize('viewOrders', $vendor);
        abort_unless($vendorOrder->vendor_id === $vendor->id, 404);

        $order = $vendorOrder->order ?? Order::find($vendorOrder->order_id);
        $data = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01|max:'.($order?->total_amount ?? 999999),
            'refund_reason' => 'nullable|string|max:500',
        ]);

        $this->adminOrders->refund(
            $vendorOrder,
            $request->user(),
            (float) $data['refund_amount'],
            $data['refund_reason'] ?? null
        );

        return back()->with('success', 'Refund processed successfully.');
    }

    public function downloadOrderInvoice(Vendor $vendor, VendorOrderMapping $vendorOrder)
    {
        Gate::authorize('viewOrders', $vendor);
        abort_unless($vendorOrder->vendor_id === $vendor->id, 404);

        $vendorOrder->load(['order.user', 'order.items.product', 'vendor.profile']);
        $filename = $this->vendorOrders->invoiceFilename($vendorOrder);

        return response($this->vendorOrders->buildOrderPdfBinary($vendorOrder, 'invoice'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function vendorRevenue(Vendor $vendor): View
    {
        $vendor->load('profile');

        return view('admin.vendors.vendor-revenue', [
            'vendor' => $vendor,
            'revenue' => $this->revenueService->forVendor($vendor),
            'metrics' => $this->vendorMetrics->forVendor($vendor),
        ]);
    }

    public function activity(Vendor $vendor): View
    {
        $vendor->load('profile');

        return view('admin.vendors.activity', [
            'vendor' => $vendor,
            'timeline' => $this->activityService->timeline($vendor),
        ]);
    }

    public function resetPassword(Request $request, Vendor $vendor): RedirectResponse
    {
        $request->validate([
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user = $vendor->user;
        if (! $user) {
            return back()->with('error', 'Vendor has no linked user account.');
        }

        $password = $request->filled('password') ? $request->password : Str::password(12);
        $user->password = Hash::make($password);
        $user->save();

        return back()->with('success', $request->filled('password')
            ? 'Vendor password updated.'
            : "Vendor password reset. Temporary password: {$password}");
    }

    public function notify(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $user = $vendor->user;
        if ($user) {
            $user->notify(new AdminNotification($data['title'], $data['message'], [
                'vendor_id' => $vendor->id,
                'type' => 'vendor_admin_message',
            ]));
        }

        return back()->with('success', 'Notification sent to vendor.');
    }

    public function verifyVendor(Vendor $vendor): RedirectResponse
    {
        foreach ($vendor->documents as $doc) {
            if ($doc->verification_status !== 'verified') {
                $this->documents->verify($doc, request()->user(), 'verified', 'Bulk verified by admin');
            }
        }

        return back()->with('success', 'Vendor documents marked as verified.');
    }

    public function approveProduct(Request $request, Vendor $vendor, VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);
        $this->adminProducts->approve($vendorProduct, $request->user(), $request->input('notes'));

        return back()->with('success', 'Product approved.');
    }

    public function rejectProduct(Request $request, Vendor $vendor, VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);
        $request->validate(['reason' => 'required|string|max:1000']);
        $this->adminProducts->reject($vendorProduct, $request->user(), $request->input('reason'));

        return back()->with('success', 'Product rejected.');
    }

    public function enableProduct(Request $request, Vendor $vendor, VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);
        $this->adminProducts->enableByAdmin($vendorProduct, $request->user());

        return back()->with('success', 'Product enabled on the marketplace.');
    }

    public function disableProduct(Request $request, Vendor $vendor, VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);
        $this->adminProducts->disableByAdmin($vendorProduct, $request->user(), $request->input('reason'));

        return back()->with('success', 'Product disabled. It is no longer visible on the marketplace.');
    }

    public function toggleProduct(Request $request, Vendor $vendor, VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);
        $updated = $this->adminProducts->toggle($vendorProduct, $request->user());

        return back()->with('success', $updated->disabled_by_admin
            ? 'Product disabled by admin.'
            : 'Product enabled on the marketplace.');
    }

    public function featureProduct(Vendor $vendor, VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);
        $product = $vendorProduct->product;
        if ($product) {
            $product->update(['is_featured' => ! $product->is_featured]);
        }

        return back()->with('success', 'Product featured status updated.');
    }

    public function destroyProduct(Vendor $vendor, VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('manageProducts', $vendor);
        abort_unless($vendorProduct->vendor_id === $vendor->id, 404);
        $this->adminProducts->removeListing($vendorProduct);

        return back()->with('success', 'Product removed.');
    }

    public function edit(Vendor $vendor)
    {
        $vendor->load('profile');

        return view('admin.vendors.edit', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:32',
            'trade_license_number' => 'nullable|string|max:100',
            'vendor_type' => ['nullable', \Illuminate\Validation\Rule::in(\App\Enums\VendorType::values())],
            'emirate' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'google_maps_location' => 'nullable|string|max:500',
            'bank_name' => 'nullable|string|max:191',
            'iban' => 'nullable|string|max:64',
            'account_holder_name' => 'nullable|string|max:191',
            'delivery_radius' => 'nullable|numeric|min:0|max:10000',
            'operating_hours' => 'nullable|string|max:500',
            'minimum_order_amount' => 'nullable|numeric|min:0|max:1000000',
            'tax_vat_number' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'logo' => 'nullable|image|max:5120',
        ]);

        $this->registration->updateProfile($vendor, $data, $request->file('logo'));

        if ($request->has('commission_rate')) {
            $vendor->update([
                'commission_rate' => $request->filled('commission_rate')
                    ? round((float) $request->input('commission_rate'), 2)
                    : null,
            ]);
        }

        return redirect()->route('admin.vendors.show', $vendor)->with('success', 'Vendor profile updated.');
    }

    public function approve(Request $request, Vendor $vendor)
    {
        $this->approval->approve($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor approved.');
    }

    public function reject(Request $request, Vendor $vendor)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        $this->approval->reject($vendor, $request->user(), $request->input('reason'), $request->input('notes'));

        return back()->with('success', 'Vendor rejected.');
    }

    public function suspend(Request $request, Vendor $vendor)
    {
        $this->approval->suspend($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor suspended.');
    }

    public function activate(Request $request, Vendor $vendor)
    {
        $this->approval->activate($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor activated.');
    }

    public function underReview(Request $request, Vendor $vendor)
    {
        $this->approval->underReview($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor marked as under review.');
    }

    public function disable(Request $request, Vendor $vendor)
    {
        $this->approval->disable($vendor, $request->user(), $request->input('notes'));

        return back()->with('success', 'Vendor disabled.');
    }

    public function destroy(Request $request, Vendor $vendor)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $this->approval->permanentlyDelete($vendor, $request->user(), $request->input('notes'));

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor permanently deleted.');
    }

    public function verifyDocument(Request $request, Vendor $vendor, int $document)
    {
        $doc = $vendor->documents()->findOrFail($document);
        $data = $request->validate([
            'verification_status' => ['required', Rule::in(['verified', 'rejected'])],
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $this->documents->verify($doc, $request->user(), $data['verification_status'], $data['admin_notes'] ?? null);

        return back()->with('success', 'Document verification updated.');
    }

    public function analytics(Request $request, Vendor $vendor)
    {
        $vendor->load('profile', 'user');
        $period = $this->performanceAnalytics->normalizePeriod((string) $request->query('period', 'month'));
        $analytics = $this->performanceAnalytics->build($vendor, $period);
        $shares = VendorAnalyticsShare::query()
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.vendors.analytics', compact('vendor', 'analytics', 'period', 'shares'));
    }
}
