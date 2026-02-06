<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class OrderExportService
{
    /**
     * Build the orders query with optional filters.
     *
     * @param  array{date_from?: string, date_to?: string, order_status?: string, payment_status?: string, package_id?: int}  $filters
     * @return Builder<Order>
     */
    public function getQuery(array $filters = []): Builder
    {
        $query = Order::with(['user', 'package', 'items.product'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);
        }
        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (isset($filters['package_id']) && $filters['package_id'] !== '' && $filters['package_id'] !== null) {
            $query->where('package_id', (int) $filters['package_id']);
        }

        return $query;
    }

    /**
     * Build export rows (header + data rows) from the given query.
     *
     * @return array<int, array<int, string|float>>
     */
    public function buildRows(Builder $query): array
    {
        $rows = [];
        $rows[] = ['Order ID', 'Date', 'Customer', 'Email', 'Package', 'Total', 'Payment Status', 'Order Status', 'Items'];

        foreach ($query->get() as $order) {
            $packageName = $order->package_id && $order->package ? $order->package->name : '';
            $itemsSummary = $order->items->map(function ($item) {
                $name = $item->product ? $item->product->name : 'Product #' . $item->product_id;

                return $name . ' x' . $item->quantity;
            })->implode('; ');
            if ($packageName && ! $itemsSummary) {
                $itemsSummary = $packageName . ' (1)';
            }
            $rows[] = [
                $order->id,
                $order->created_at->format('Y-m-d H:i'),
                $order->user ? $order->user->name : '',
                $order->user ? $order->user->email : '',
                $packageName,
                $order->total_amount,
                $order->payment_status,
                $order->order_status,
                $itemsSummary,
            ];
        }

        return $rows;
    }

    /**
     * Get filters from a request (export or send-to-supplier).
     *
     * @return array{date_from?: string, date_to?: string, order_status?: string, payment_status?: string, package_id?: int|null}
     */
    public function filtersFromRequest(\Illuminate\Http\Request $request): array
    {
        $filters = [];
        if ($request->filled('date_from')) {
            $filters['date_from'] = $request->date_from;
        }
        if ($request->filled('date_to')) {
            $filters['date_to'] = $request->date_to;
        }
        if ($request->filled('order_status')) {
            $filters['order_status'] = $request->order_status;
        }
        if ($request->filled('payment_status')) {
            $filters['payment_status'] = $request->payment_status;
        }
        if ($request->filled('package_id')) {
            $filters['package_id'] = (int) $request->package_id;
        }

        return $filters;
    }
}
