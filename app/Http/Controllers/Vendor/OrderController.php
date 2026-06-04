<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Services\Vendor\VendorOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly VendorOrderService $orders
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function index(Request $request): View
    {
        $vendor = $this->vendor($request);
        $orders = $this->orders->listForVendor($vendor, $request->only(['status', 'search']), 15);

        return view('vendor.orders.index', compact('orders', 'vendor'));
    }

    public function show(Request $request, int $mapping): View|RedirectResponse
    {
        $vendor = $this->vendor($request);
        $orderMapping = VendorOrderMapping::with(['order.user', 'order.items.product', 'statusLogs.changedByUser'])
            ->where('vendor_id', $vendor->id)
            ->where('id', $mapping)
            ->first();

        if ($orderMapping === null) {
            return redirect()->route('vendor.orders.index')->with('error', 'Order not found.');
        }

        return view('vendor.orders.show', [
            'mapping' => $orderMapping,
            'statuses' => VendorOrderStatus::cases(),
        ]);
    }

    public function updateStatus(Request $request, int $mapping): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $orderMapping = VendorOrderMapping::where('vendor_id', $vendor->id)->where('id', $mapping)->first();
        if ($orderMapping === null) {
            return redirect()->route('vendor.orders.index')->with('error', 'Order not found.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(VendorOrderStatus::values())],
            'note' => 'nullable|string|max:500',
        ]);

        $this->orders->updateStatus(
            $orderMapping,
            VendorOrderStatus::from($data['status']),
            $request->user(),
            $data['note'] ?? null
        );

        return redirect()->route('vendor.orders.show', $mapping)->with('success', 'Order status updated.');
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor');
    }
}
