<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Enums\VendorDisputeStatus;
use App\Enums\VendorOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\VendorOrderMapping;
use App\Services\Vendor\AdminVendorOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceOrderController extends Controller
{
    public function __construct(
        private readonly AdminVendorOrderService $orders
    ) {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $demoMarker = \Database\Seeders\VendorDemoOrdersSeeder::DEMO_MARKER;

        $orders = VendorOrderMapping::with(['order.user', 'vendor.profile'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->query('vendor_id')))
            ->when($request->filled('dispute'), fn ($q) => $q->whereNotNull('dispute_status'))
            ->when($request->boolean('exclude_demo'), function ($q) use ($demoMarker) {
                $q->whereHas('order', function ($oq) use ($demoMarker) {
                    $oq->where(function ($inner) use ($demoMarker) {
                        $inner->whereNull('special_instructions')
                            ->orWhere('special_instructions', 'not like', $demoMarker.'%');
                    });
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->where('order_id', 'like', "%{$s}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.marketplace.orders.index', compact('orders'));
    }

    public function show(VendorOrderMapping $vendorOrder)
    {
        $vendorOrder->load(['order.user', 'order.items.product', 'vendor.profile', 'statusLogs.changedByUser', 'cancelledByUser']);

        return view('admin.marketplace.orders.show', compact('vendorOrder'));
    }

    public function updateStatus(Request $request, VendorOrderMapping $vendorOrder)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(VendorOrderStatus::values())],
            'note' => 'nullable|string|max:500',
        ]);

        $this->orders->updateStatus($vendorOrder, VendorOrderStatus::from($data['status']), $request->user(), $data['note'] ?? null);
        $this->orders->recalculateCommission($vendorOrder->fresh());

        return back()->with('success', 'Order status updated.');
    }

    public function cancel(Request $request, VendorOrderMapping $vendorOrder)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        $this->orders->cancel($vendorOrder, $request->user(), $request->input('reason'));

        return back()->with('success', 'Order cancelled.');
    }

    public function updateDispute(Request $request, VendorOrderMapping $vendorOrder)
    {
        $data = $request->validate([
            'dispute_status' => ['required', Rule::in(VendorDisputeStatus::values())],
            'dispute_notes' => 'nullable|string|max:2000',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $this->orders->updateDispute($vendorOrder, VendorDisputeStatus::from($data['dispute_status']), $request->user(), $data['dispute_notes'] ?? null);
        if (! empty($data['admin_notes'])) {
            $vendorOrder->update(['admin_notes' => $data['admin_notes']]);
        }

        return back()->with('success', 'Dispute updated.');
    }
}
