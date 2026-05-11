<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Services\ShopOrderCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderActionsController extends Controller
{
    public function __construct(
        private ShopOrderCancellationService $orderCancellation,
    ) {}

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::query()->findOrFail($id);

        if ($this->orderCancellation->isForbidden((string) ($order->order_status ?? 'pending'))) {
            return response()->json([
                'success' => false,
                'message' => $this->orderCancellation->forbiddenMessage((string) ($order->order_status ?? 'pending')),
            ], 422);
        }

        $refund = $this->orderCancellation->cancelOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'data' => $this->payload($order->fresh()),
            'refund' => $refund,
        ]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $order = Order::query()->findOrFail($id);

        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01|max:' . (float) $order->total_amount,
            'refund_reason' => 'nullable|string|max:500',
        ]);

        Transaction::query()->create([
            'transaction_id' => 'REF-' . Str::upper(Str::random(12)),
            'transactionable_type' => Order::class,
            'transactionable_id' => $order->id,
            'type' => 'refund',
            'gateway' => $order->payment_method ?? 'manual',
            'amount' => $validated['refund_amount'],
            'currency' => 'AED',
            'status' => 'completed',
            'notes' => $validated['refund_reason'] ?? 'Admin refund',
            'processed_at' => now(),
        ]);

        $order->payment_status = 'refunded';
        $order->refunded_at = now();
        $order->refund_amount = (float) $validated['refund_amount'];
        $order->refund_reason = $validated['refund_reason'] ?? null;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Refund processed successfully.',
            'data' => $this->payload($order->fresh()),
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:orders,id',
        ]);

        $ids = collect($validated['ids'])->map(fn ($id) => (int) $id)->unique()->values();

        DB::transaction(function () use ($ids) {
            OrderItem::query()->whereIn('order_id', $ids)->delete();
            Order::query()->whereIn('id', $ids)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Selected orders deleted successfully.',
            'data' => [
                'deleted_count' => $ids->count(),
                'deleted_ids' => $ids->all(),
            ],
        ]);
    }

    private function payload(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'order_status' => (string) ($order->order_status ?? 'pending'),
            'payment_status' => (string) ($order->payment_status ?? 'pending'),
            'total_amount' => (float) $order->total_amount,
            'refund_amount' => $order->refund_amount !== null ? (float) $order->refund_amount : null,
            'refund_reason' => $order->refund_reason,
            'refunded_at' => $order->refunded_at?->toIso8601String(),
        ];
    }
}
