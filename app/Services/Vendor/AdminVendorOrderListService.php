<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminVendorOrderListService
{
    /**
     * @return array<string, float|int>
     */
    public function stats(Vendor $vendor): array
    {
        $base = VendorOrderMapping::query()->where('vendor_id', $vendor->id);
        $cancelled = VendorOrderStatus::Cancelled->value;
        $pending = VendorOrderStatus::Pending->value;
        $processingStatuses = [
            VendorOrderStatus::Confirmed->value,
            VendorOrderStatus::Processing->value,
            VendorOrderStatus::Shipped->value,
        ];

        $activeQuery = (clone $base)->where('status', '!=', $cancelled);
        $totalRevenue = (float) (clone $activeQuery)->sum('total_amount');
        $totalCommission = (float) (clone $activeQuery)->sum('commission_amount');

        $refunded = (clone $base)
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'refunded'))
            ->count();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', $pending)->count(),
            'processing' => (clone $base)->whereIn('status', $processingStatuses)->count(),
            'delivered' => (clone $base)->where('status', VendorOrderStatus::Delivered->value)->count(),
            'cancelled' => (clone $base)->where('status', $cancelled)->count(),
            'refunded' => $refunded,
            'total_revenue' => round($totalRevenue, 2),
            'vendor_earnings' => round(max(0, $totalRevenue - $totalCommission), 2),
            'platform_commission' => round($totalCommission, 2),
        ];
    }

    public function paginate(Vendor $vendor, Request $request): LengthAwarePaginator
    {
        $query = VendorOrderMapping::query()
            ->with(['order.user', 'order.items.product'])
            ->where('vendor_id', $vendor->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('payment_status')) {
            $query->whereHas('order', fn ($q) => $q->where('payment_status', $request->query('payment_status')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('order.user', fn ($uq) => $uq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'amount_high' => $query->orderByDesc('total_amount'),
            'amount_low' => $query->orderBy('total_amount'),
            default => $query->latest(),
        };

        return $query->paginate($request->integer('per_page', 20))->withQueryString();
    }

    public function exportCsv(Vendor $vendor, Request $request): StreamedResponse
    {
        $query = VendorOrderMapping::query()
            ->with(['order.user', 'order.items.product'])
            ->where('vendor_id', $vendor->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('payment_status')) {
            $query->whereHas('order', fn ($q) => $q->where('payment_status', $request->query('payment_status')));
        }
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->get();
        $filename = 'vendor-'.$vendor->id.'-orders-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($orders, $vendor) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order ID',
                'Customer',
                'Email',
                'Phone',
                'Products',
                'Order Total (AED)',
                'Vendor Earnings (AED)',
                'Commission (AED)',
                'Payment Method',
                'Payment Status',
                'Order Status',
                'Tracking',
                'Order Date',
            ]);

            foreach ($orders as $mapping) {
                $order = $mapping->order;
                $items = $order?->items?->map(fn ($i) => ($i->product?->name ?? 'Item').' x'.$i->quantity)->implode('; ') ?? '';
                $earnings = max(0, (float) $mapping->total_amount - (float) $mapping->commission_amount);

                fputcsv($handle, [
                    $mapping->order_id,
                    $order?->user?->name ?? $order?->guest_full_name ?? 'Guest',
                    $order?->user?->email ?? $order?->guest_email ?? '',
                    $order?->user?->phone ?? $order?->guest_phone ?? '',
                    $items,
                    $mapping->total_amount,
                    round($earnings, 2),
                    $mapping->commission_amount,
                    $order?->payment_method ?? '',
                    $order?->payment_status ?? '',
                    $mapping->status,
                    $mapping->tracking_number ?? '',
                    $mapping->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
