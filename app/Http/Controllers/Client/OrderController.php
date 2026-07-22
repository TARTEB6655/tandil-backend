<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Support\OrderClientReportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $orders = Order::where('user_id', $user->id)
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('client.orders.index', compact('orders'));
    }

    public function show($id)
    {
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)
            ->with([
                'items.product.category',
                'items.product.primaryImage',
                'items.product.images',
                'items.product.firstImage',
                'shippingAddress',
                'user',
            ])
            ->findOrFail($id);

        $reportMeta = app(OrderClientReportService::class)->serviceReportMetaForOrder($order);

        $status = strtolower((string) ($order->order_status ?? 'pending'));
        $canRate = in_array($status, ['completed', 'delivered'], true);

        $reviews = Review::where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->get();
        $serviceReview = $reviews->firstWhere('product_id', null);
        $productReviews = $reviews->whereNotNull('product_id')->keyBy('product_id');

        return view('client.orders.show', compact(
            'order',
            'reportMeta',
            'canRate',
            'serviceReview',
            'productReviews'
        ));
    }

    /**
     * Full service report for a shop order (web). Shop-order reports are linked
     * to a visit via order_id / [SHOP-ORDER:id] notes, so they are not covered
     * by the subscription-scoped client.reports.show route. Reuses the same
     * formatter as the mobile API and is gated on report visibility.
     */
    public function report($id)
    {
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)->findOrFail($id);

        $reportService = app(OrderClientReportService::class);
        $report = $reportService->findReportForOrder($order);
        if (! $reportService->isReportVisibleToClient($report)) {
            return redirect()
                ->route('client.orders.show', $order->id)
                ->with('error', 'The service report is not available yet.');
        }

        $reportData = $reportService->formatReportForClient($report);

        return view('client.orders.report', compact('order', 'reportData'));
    }

    /**
     * Client confirms they received the order. Mirrors the mobile API
     * (POST /api/orders/{id}/mark-delivered): the service report must be
     * visible and the order must be completed first.
     */
    public function markDelivered($id)
    {
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)->findOrFail($id);

        $reportService = app(OrderClientReportService::class);
        $report = $reportService->findReportForOrder($order);
        if (! $reportService->isReportVisibleToClient($report)) {
            return back()->with('error', 'Service report must be available before marking the order as delivered.');
        }

        $status = strtolower((string) ($order->order_status ?? 'pending'));
        if ($status === 'delivered') {
            return back()->with('success', 'Order is already marked as delivered.');
        }
        if ($status !== 'completed') {
            return back()->with('error', 'Order must be completed before it can be marked as delivered.');
        }

        $order->order_status = 'delivered';
        $order->save();

        return back()->with('success', 'Order marked as delivered. Thank you!');
    }

    /**
     * Client rates the service (and optionally individual products). Mirrors the
     * mobile API (POST /api/orders/{id}/rate) and reuses the same Review storage
     * and product rating aggregation.
     */
    public function rate(Request $request, $id)
    {
        $user = Auth::user();
        $order = Order::with('items')->where('user_id', $user->id)->findOrFail($id);

        $status = strtolower((string) ($order->order_status ?? 'pending'));
        if (! in_array($status, ['completed', 'delivered'], true)) {
            return back()->with('error', 'You can rate the service once the order is completed.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'product_ratings' => 'nullable|array',
            'product_ratings.*.product_id' => 'required|integer',
            'product_ratings.*.rating' => 'required|integer|min:1|max:5',
            'product_ratings.*.review' => 'nullable|string|max:1000',
        ]);

        $orderProductIds = $order->items->pluck('product_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();

        $productRatings = [];
        if (! empty($validated['product_ratings'])) {
            foreach ($validated['product_ratings'] as $entry) {
                $pid = (int) $entry['product_id'];
                if (! $orderProductIds->contains($pid)) {
                    return back()->with('error', "Product #{$pid} is not part of this order.");
                }
                $productRatings[$pid] = [
                    'rating' => (int) $entry['rating'],
                    'review' => $entry['review'] ?? null,
                ];
            }
        } else {
            foreach ($orderProductIds as $pid) {
                $productRatings[$pid] = [
                    'rating' => (int) $validated['rating'],
                    'review' => $validated['review'] ?? null,
                ];
            }
        }

        DB::transaction(function () use ($order, $user, $validated, $productRatings) {
            Review::updateOrCreate(
                ['user_id' => $user->id, 'order_id' => $order->id, 'product_id' => null],
                ['rating' => (int) $validated['rating'], 'comment' => $validated['review'] ?? null]
            );

            foreach ($productRatings as $pid => $data) {
                Review::updateOrCreate(
                    ['user_id' => $user->id, 'order_id' => $order->id, 'product_id' => $pid],
                    ['rating' => $data['rating'], 'comment' => $data['review']]
                );
            }
        });

        foreach (Product::whereIn('id', array_keys($productRatings))->get() as $product) {
            $product->recalculateRating();
        }

        return back()->with('success', 'Thank you! Your rating has been submitted.');
    }
}
