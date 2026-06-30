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

class VendorOrderController extends Controller
{
    public function __construct(
        private readonly VendorOrderService $orders
    ) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $paginator = $this->orders->listForVendor($vendor, $request->only(['status', 'search']), (int) $request->query('per_page', 15));

        return ApiResponse::success('Orders retrieved.', [
            'summary' => $this->orders->statusSummary($vendor),
            'items' => $paginator->items(),
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
        $mapping = VendorOrderMapping::with(['order.user', 'order.items.product', 'statusLogs'])
            ->where('vendor_id', $vendor->id)
            ->where('id', $id)
            ->first();

        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success('Order retrieved.', ['order_mapping' => $mapping]);
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
        ]);

        $status = VendorOrderStatus::from($data['status']);
        $mapping = $this->orders->updateStatus($mapping, $status, $request->user(), $data['note'] ?? null);

        return ApiResponse::success('Order status updated.', ['order_mapping' => $mapping]);
    }
}
