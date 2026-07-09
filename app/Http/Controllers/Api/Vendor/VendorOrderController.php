<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Enums\VendorOrderStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorOrderMapping;
use App\Services\Vendor\VendorOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorOrderController extends Controller
{
    public function __construct(
        private readonly VendorOrderService $orders
    ) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $paginator = $this->orders->listForVendor($vendor, $request->only(['status', 'search']), (int) $request->query('per_page', 15));

        $items = collect($paginator->items())
            ->map(fn (VendorOrderMapping $mapping) => $this->orders->formatListItem($mapping))
            ->values()
            ->all();

        return ApiResponse::success('Orders retrieved.', [
            'summary' => $this->orders->statusSummary($vendor),
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $mapping = VendorOrderMapping::with([
            'order.user',
            'order.shippingAddress',
            'order.items.product.primaryImage',
            'statusLogs',
        ])
            ->where('vendor_id', $vendor->id)
            ->where('id', $id)
            ->first();

        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success('Order retrieved.', [
            'order' => $this->orders->formatDetail($mapping),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $mapping = VendorOrderMapping::where('vendor_id', $vendor->id)->where('id', $id)->first();
        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(VendorOrderStatus::values())],
            'note' => 'nullable|string|max:500',
            'tracking_number' => 'nullable|string|max:64',
        ]);

        $status = VendorOrderStatus::from($data['status']);
        $allowed = array_map(fn (VendorOrderStatus $s) => $s->value, $this->orders->allowedNextStatuses($mapping->statusEnum()));

        if ($allowed !== [] && ! in_array($status->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['This status transition is not allowed from the current order state.'],
            ]);
        }

        $mapping = $this->orders->updateStatus(
            $mapping,
            $status,
            $request->user(),
            $data['note'] ?? null,
            $data['tracking_number'] ?? null
        );

        return ApiResponse::success('Order status updated.', [
            'order' => $this->orders->formatDetail($mapping),
        ]);
    }
}
