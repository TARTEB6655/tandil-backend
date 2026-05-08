<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderActionsController extends Controller
{
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::query()->findOrFail($id);
        $status = $this->normalizeOrderTrackingStatus((string) ($order->order_status ?? 'pending'));

        if ($this->isCancellationBlockedByAssignment($status)) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled after technician assignment.',
            ], 422);
        }

        $order->order_status = 'cancelled';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully.',
            'data' => $this->payload($order->fresh()),
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

    private function normalizeOrderTrackingStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'paid' => 'confirmed',
            'processing' => 'in_progress',
            'shipped' => 'completed',
            'pending', 'confirmed', 'assigned', 'in_progress', 'completed', 'delivered', 'cancelled' => $status,
            default => $status !== '' ? $status : 'pending',
        };
    }

    private function orderTrackingRank(string $status): int
    {
        return match ($status) {
            'pending' => 0,
            'confirmed' => 1,
            'assigned' => 2,
            'in_progress' => 3,
            'completed' => 4,
            'delivered' => 5,
            default => 0,
        };
    }

    private function isCancellationBlockedByAssignment(string $status): bool
    {
        return $this->orderTrackingRank($status) >= $this->orderTrackingRank('assigned')
            || $status === 'cancelled';
    }
}

