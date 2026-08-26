<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Enums\VendorOrderStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorOrderMapping;
use App\Services\Vendor\VendorOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $perPage = (int) $request->query('per_page', 15);

        // Postman/FE often send `?status=` (empty) — treat as "all statuses".
        // Filter uses shop order_status (same values shown on list cards / track).
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $allowedStatuses = [
            'pending',
            'confirmed',
            'processing',
            'shipped',
            'delivered',
            'cancelled',
        ];
        if ($status !== '' && ! in_array($status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status. Allowed: '.implode(', ', $allowedStatuses).'.'],
            ]);
        }

        $filters = array_filter([
            'status' => $status !== '' ? $status : null,
            'search' => $search !== '' ? $search : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $paginator = $this->orders->listForVendor($vendor, $filters, $perPage);

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

    public function show(Request $request, string|int $id): JsonResponse
    {
        $mapping = $this->orders->findMappingForVendor($request->attributes->get('vendor'), $id, 'detail');

        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success('Order retrieved.', [
            'order' => $this->orders->formatDetail($mapping),
        ]);
    }

    public function track(Request $request, string|int $id): JsonResponse
    {
        $mapping = $this->orders->findMappingForVendor($request->attributes->get('vendor'), $id, 'detail');

        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success(
            'Order tracking information retrieved successfully',
            $this->orders->formatTrack($mapping)
        );
    }

    public function contact(Request $request, string|int $id): JsonResponse
    {
        $mapping = $this->orders->findMappingForVendor($request->attributes->get('vendor'), $id, 'contact');

        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        return ApiResponse::success('Customer contact retrieved.', [
            'contact' => $this->orders->formatContact($mapping),
        ]);
    }

    public function invoice(Request $request, string|int $id): Response|JsonResponse
    {
        $mapping = $this->orders->findMappingForVendor($request->attributes->get('vendor'), $id, 'pdf');

        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        $disposition = strtolower((string) $request->query('disposition', 'inline'));
        $filename = $this->orders->invoiceFilename($mapping);
        $contentDisposition = $disposition === 'attachment'
            ? 'attachment; filename="'.$filename.'"'
            : 'inline; filename="'.$filename.'"';

        return response($this->orders->buildOrderPdfBinary($mapping, 'invoice'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $contentDisposition,
        ]);
    }

    public function download(Request $request, string|int $id): Response|JsonResponse
    {
        $mapping = $this->orders->findMappingForVendor($request->attributes->get('vendor'), $id, 'pdf');

        if ($mapping === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        $filename = $this->orders->downloadFilename($mapping);

        return response($this->orders->buildOrderPdfBinary($mapping, 'order'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function updateStatus(Request $request, string|int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $mapping = $this->orders->findMappingForVendor($vendor, $id, 'detail');
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

    /**
     * Product orders: customer gives OTP to vendor; vendor confirms delivery here.
     * Path id must be vendor_order_mapping_id (list item `id`) — not shop order_id / order_XXXX.
     */
    public function confirmDelivery(Request $request, string|int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $mapping = $this->orders->findMappingForVendorByMappingIdOnly($vendor, $id, 'detail');
        if ($mapping === null) {
            return ApiResponse::error('Order not found. Use vendor_order_mapping_id from GET /api/vendor/orders (field: id).', 404);
        }

        $otp = $request->input('otp');
        if (is_scalar($otp)) {
            $request->merge(['otp' => trim((string) $otp)]);
        }

        $data = $request->validate([
            'otp' => 'required|string|min:4|max:10',
            'note' => 'nullable|string|max:500',
        ]);

        $mapping = $this->orders->confirmDeliveryWithOtp(
            $mapping,
            $data['otp'],
            $request->user()
        );

        return ApiResponse::success('Delivery confirmed with OTP.', [
            'order' => $this->orders->formatDetail($mapping),
        ]);
    }

    /**
     * Product orders: invalidate the current delivery OTP and send a new one to the customer.
     * Path id must be vendor_order_mapping_id (list item `id`) — not shop order_id / order_XXXX.
     */
    public function resendDeliveryOtp(Request $request, string|int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $mapping = $this->orders->findMappingForVendorByMappingIdOnly($vendor, $id, 'detail');
        if ($mapping === null) {
            return ApiResponse::error('Order not found. Use vendor_order_mapping_id from GET /api/vendor/orders (field: id).', 404);
        }

        $mapping = $this->orders->resendDeliveryOtp($mapping, $request->user());

        return ApiResponse::success('Delivery OTP resent to the customer.', [
            'order' => $this->orders->formatDetail($mapping),
        ]);
    }
}
