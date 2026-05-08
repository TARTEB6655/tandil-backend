<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Services\OrderExportService;
use App\Services\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $isCancelledOnly = $request->boolean('cancelled_only');
        $query = Order::with(['user', 'items']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('order_status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status') && $request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('email', 'LIKE', "%{$request->search}%");
            })->orWhere('payment_reference', 'LIKE', "%{$request->search}%");
        }

        // Apply filter tabs
        $filter = $request->get('filter', 'all');
        if ($isCancelledOnly) {
            $filter = 'archived';
        }
        if ($filter === 'unfulfilled') {
            $query->where('order_status', '!=', 'delivered')->where('order_status', '!=', 'cancelled');
        } elseif ($filter === 'unpaid') {
            $query->where('payment_status', '!=', 'paid');
        } elseif ($filter === 'open') {
            $query->whereIn('order_status', ['pending', 'processing']);
        } elseif ($filter === 'archived') {
            $query->where('order_status', 'cancelled');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        // Statistics
        $stats = [
            'total' => Order::count(),
            'total_items' => OrderItem::sum('quantity'),
            'total_revenue' => Order::where('payment_status', 'paid')->sum('total_amount'),
            'fulfilled' => Order::where('order_status', 'delivered')->count(),
            'unfulfilled' => Order::where('order_status', '!=', 'delivered')->where('order_status', '!=', 'cancelled')->count(),
            'unpaid' => Order::where('payment_status', '!=', 'paid')->count(),
            'open' => Order::whereIn('order_status', ['pending', 'processing'])->count(),
            'archived' => Order::where('order_status', 'cancelled')->count(),
        ];

        $packages = Package::orderBy('sort_order')->get(['id', 'name']);
        $pageTitle = $isCancelledOnly ? 'Cancelled Orders' : null;

        return view('admin.orders.index', compact('orders', 'stats', 'filter', 'packages', 'pageTitle'));
    }

    public function cancelled(Request $request)
    {
        $request->merge([
            'filter' => 'archived',
            'cancelled_only' => true,
        ]);

        return $this->index($request);
    }

    /**
     * Export orders to CSV or Excel (web download). Query: date_from, date_to, order_status, payment_status, package_id, format=csv|xlsx.
     */
    public function export(Request $request, OrderExportService $exportService, SimpleXlsxWriter $xlsxWriter): StreamedResponse|\Illuminate\Http\Response
    {
        $filters = $exportService->filtersFromRequest($request);
        $query = $exportService->getQuery($filters);
        $format = strtolower($request->input('format', 'csv'));

        if ($format === 'xlsx') {
            $rows = $exportService->buildRows($query);
            $filename = 'orders_' . now()->format('Y-m-d_His') . '.xlsx';
            $content = $xlsxWriter->generate($rows);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        $filename = 'orders_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($exportService, $query) {
            $rows = $exportService->buildRows($query);
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Send orders export to supplier by email (web form POST).
     */
    public function sendToSupplier(Request $request, OrderExportService $exportService)
    {
        $request->validate([
            'email' => 'nullable|email',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'package_id' => 'nullable|integer|exists:packages,id',
        ]);

        $filters = $exportService->filtersFromRequest($request);
        $query = $exportService->getQuery($filters);
        $rows = $exportService->buildRows($query);

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $filename = 'orders_' . now()->format('Y-m-d_His') . '.csv';
        $to = $request->input('email') ?: config('mail.supplier_email', config('mail.from.address'));

        if (empty($to)) {
            return redirect()->back()->with('error', 'No supplier email configured. Set MAIL_SUPPLIER_EMAIL in .env or enter an email below.');
        }

        try {
            Mail::raw('Please find the orders export attached.', function ($message) use ($to, $filename, $csv) {
                $message->to($to)
                    ->subject('Orders Export - ' . now()->format('Y-m-d H:i'))
                    ->attachData($csv, $filename, ['mime' => 'text/csv']);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Orders export sent to ' . $to . '.');
    }

    public function show($id)
    {
        $order = Order::with(['user', 'items.product.category', 'transactions'])->findOrFail($id);
        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'order_status' => 'required|in:pending,processed,delivered,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['order_status' => $request->order_status]);

        return redirect()->back()->with('success', 'Order status updated successfully');
    }

    public function markPaid($id)
    {
        $order = Order::findOrFail($id);
        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Order marked as paid');
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Can only cancel pending or processing orders
        if (!in_array($order->order_status, ['pending', 'processing'])) {
            return redirect()->back()->with('error', 'Only pending or processing orders can be cancelled.');
        }

        $order->update([
            'order_status' => 'cancelled',
        ]);

        return redirect()->back()->with('success', 'Order cancelled successfully.');
    }

    /**
     * Process refund for an order.
     */
    public function refund(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01|max:' . $order->total_amount,
            'refund_reason' => 'nullable|string|max:500',
        ]);

        // Create refund transaction
        $transaction = \App\Models\Transaction::create([
            'transaction_id' => 'REF-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(12)),
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

        // Update order
        $order->update([
            'payment_status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $validated['refund_amount'],
            'refund_reason' => $validated['refund_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Refund processed successfully. Transaction ID: ' . $transaction->transaction_id);
    }

    /**
     * Permanently delete an order and its items.
     */
    public function destroy($id)
    {
        $order = Order::with('items')->findOrFail($id);

        \DB::transaction(function () use ($order) {
            $order->items()->delete();
            $order->delete();
        });

        return redirect()->route('admin.orders.index')->with('success', 'Order deleted successfully.');
    }

    /**
     * Bulk delete selected orders.
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:orders,id',
        ]);

        $ids = collect($validated['ids'])->map(fn ($id) => (int) $id)->unique()->values();

        \DB::transaction(function () use ($ids) {
            OrderItem::query()->whereIn('order_id', $ids)->delete();
            Order::query()->whereIn('id', $ids)->delete();
        });

        return redirect()->route('admin.orders.index')->with('success', $ids->count().' order(s) deleted successfully.');
    }
}


