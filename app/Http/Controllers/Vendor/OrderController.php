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
        // Web table does not need product image summaries — skip that join work.
        $orders = $this->orders->listForVendor(
            $vendor,
            $request->only(['status', 'search']),
            15,
            withProductSummaries: false
        );

        return view('vendor.orders.index', compact('orders', 'vendor'));
    }

    public function show(Request $request, int $mapping): View|RedirectResponse
    {
        $vendor = $this->vendor($request);
        $orderMapping = $this->orders->findMappingForVendor($vendor, $mapping, 'detail');

        if ($orderMapping === null) {
            return redirect()->route('vendor.orders.index')->with('error', 'Order not found.');
        }

        $detail = $this->orders->formatDetail($orderMapping);
        $contact = $this->orders->formatContact($orderMapping);
        $allowedStatuses = $this->orders->allowedNextStatuses($orderMapping->statusEnum());
        $orderNumber = $this->orders->orderNumber($orderMapping);

        return view('vendor.orders.show', [
            'mapping' => $orderMapping,
            'detail' => $detail,
            'contact' => $contact,
            'allowedStatuses' => $allowedStatuses,
            'orderNumber' => $orderNumber,
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
            'tracking_number' => 'nullable|string|max:64',
        ]);

        $status = VendorOrderStatus::from($data['status']);
        $allowed = array_map(
            fn (VendorOrderStatus $s) => $s->value,
            $this->orders->allowedNextStatuses($orderMapping->statusEnum())
        );

        if ($allowed !== [] && ! in_array($status->value, $allowed, true)) {
            return redirect()->route('vendor.orders.show', $mapping)
                ->withErrors(['status' => 'This status transition is not allowed from the current order state.']);
        }

        $this->orders->updateStatus(
            $orderMapping,
            $status,
            $request->user(),
            $data['note'] ?? null,
            $data['tracking_number'] ?? null
        );

        return redirect()->route('vendor.orders.show', $mapping)->with('success', 'Order status updated.');
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor');
    }
}
