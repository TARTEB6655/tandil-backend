<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\User;
use App\Http\Requests\StoreSubscriptionRequest;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        // Only authenticated users with client or admin role
        // Exclude plans() method as it's public
        $this->middleware('auth:sanctum')->except('plans');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $subs = Subscription::with('client')->orderBy('created_at', 'desc')->get();
        } else {
            // Clients only see their own subscriptions
            $subs = Subscription::where('client_id', $user->id)->with('visits')->orderBy('created_at', 'desc')->get();
        }

        // Return full data array so list APIs show all fields in Postman
        return response()->json([
            'success' => true,
            'message' => 'Subscriptions retrieved successfully.',
            'data' => $subs->toArray(),
            'total' => $subs->count(),
        ]);
    }

    /**
     * Return available plans and their prices.
     */
    public function plans()
    {
        $priceMap = [
            '1_month' => 500.00,
            '3_month' => 1450.00,
            '6_month' => 2900.00,
            '12_month' => 5500.00,
        ];

        $plans = collect($priceMap)->map(function ($price, $plan) {
            return [
                'plan' => $plan,
                'price' => $price,
                'label' => str_replace('_', ' ', $plan),
            ];
        })->values();

        return ApiResponse::success('Plans retrieved successfully.', $plans);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $sub = Subscription::with('visits')->findOrFail($id);

        if (! ($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return ApiResponse::error('Forbidden', 403);
        }

        return ApiResponse::success('Subscription retrieved successfully.', $sub);
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $user = $request->user();
        // Only admins may create subscriptions (admin creates plans/subscriptions for clients)
        if (! $user->hasRole('admin')) {
            return ApiResponse::error('Forbidden: only admin can create subscriptions', 403);
        }

        $data = $request->validated();

        // Helper to create a single subscription and its visits
        $createForClient = function ($data, $clientId) use ($user) {
            $d = $data;
            $d['client_id'] = $clientId;

            // Determine start_date (default to today if missing)
            $start = isset($d['start_date']) && $d['start_date']
                ? Carbon::parse($d['start_date'])
                : Carbon::today();
            $d['start_date'] = $start->toDateString();

            // Compute end_date based on plan length (if not provided)
            if (!isset($d['end_date']) || !$d['end_date']) {
                $planMap = ['1_month' => 1, '3_month' => 3, '6_month' => 6, '12_month' => 12];
                $months = $planMap[$d['plan']] ?? 1;
                $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
                $d['end_date'] = $end->toDateString();
            } else {
                $d['end_date'] = Carbon::parse($d['end_date'])->toDateString();
            }

            if (!isset($d['payment_status'])) {
                $d['payment_status'] = 'pending';
            }

            // Load prices if amount missing
            if (!isset($d['amount']) || empty($d['amount'])) {
                $planMap = ['1_month' => 1, '3_month' => 3, '6_month' => 6, '12_month' => 12];
                $months = $planMap[$d['plan']] ?? 1;
                $plansConfig = config('subscriptions.plans', []);
                $d['amount'] = isset($plansConfig[$d['plan']]['price'])
                    ? (float) $plansConfig[$d['plan']]['price']
                    : (500.00 * $months);
            }

            if (!isset($d['total_visits']) || $d['total_visits'] === null) {
                $planMap = ['1_month' => 1, '3_month' => 3, '6_month' => 6, '12_month' => 12];
                $d['total_visits'] = $planMap[$d['plan']] ?? 1;
            }

            if (!isset($d['completed_visits']) || $d['completed_visits'] === null) {
                $d['completed_visits'] = 0;
            }

            if ($d['payment_status'] === 'paid' && !isset($d['paid_at'])) {
                $d['paid_at'] = now();
            }

            $sub = Subscription::create($d);

            // Generate visits
            $visits = [];
            $totalVisits = $d['total_visits'];
            $startDate = Carbon::parse($d['start_date']);
            $endDate = Carbon::parse($d['end_date']);
            $daysDiff = $startDate->diffInDays($endDate);
            $visitInterval = $totalVisits > 1 ? floor($daysDiff / ($totalVisits - 1)) : 0;

            for ($i = 0; $i < $totalVisits; $i++) {
                if ($i === 0) {
                    $scheduled = $startDate->toDateString();
                } else {
                    $scheduled = $startDate->copy()->addDays($visitInterval * $i)->toDateString();
                    if (Carbon::parse($scheduled)->gt($endDate)) {
                        $scheduled = $endDate->toDateString();
                    }
                }

                $visit = Visit::create([
                    'subscription_id' => $sub->id,
                    'scheduled_date' => $scheduled,
                    'status' => 'pending',
                ]);
                $visits[] = $visit;
            }

            $sub->load('visits');

            try {
                $sub->client?->notify(new \App\Notifications\SubscriptionCreated($sub));
            } catch (\Throwable $e) {
                // ignore notify errors
            }

            return $sub;
        };

        // If admin wants to apply to multiple clients or all clients
        $created = [];
        if ($user->hasRole('admin') && (!empty($data['apply_to_all']) || !empty($data['client_ids']))) {
            if (!empty($data['apply_to_all'])) {
                $clientIds = User::role('client')->pluck('id')->toArray();
            } else {
                $clientIds = $data['client_ids'] ?? [];
            }

            foreach ($clientIds as $cid) {
                $created[] = $createForClient($data, $cid);
            }

            return ApiResponse::success('Subscriptions created successfully.', $created, 201);
        }

        // Default single subscription creation (client_id resolved above in request)
        if (!isset($data['client_id'])) {
            $data['client_id'] = $user->id;
        } elseif (!$user->hasRole('admin')) {
            $data['client_id'] = $user->id;
        }

        $sub = $createForClient($data, $data['client_id']);

        return ApiResponse::success('Subscription created successfully.', $sub, 201);
    }

    /**
     * Update subscription
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $sub = Subscription::findOrFail($id);

        // Only admins or the owner can update
        if (!($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'payment_status' => 'nullable|string|in:pending,paid,failed,refunded,cancelled',
            'amount' => 'nullable|numeric|min:0',
            'total_visits' => 'nullable|integer|min:0',
            'completed_visits' => 'nullable|integer|min:0',
            'payment_reference' => 'nullable|string|max:255',
            'plan_name' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'features' => 'nullable|array',
        ]);

        if ($request->has('start_date')) {
            $sub->start_date = $request->input('start_date');
        }
        if ($request->has('end_date')) {
            $sub->end_date = $request->input('end_date');
        }
        if ($request->has('payment_status') && $user->hasRole('admin')) {
            $sub->payment_status = $request->input('payment_status');
        }
        if ($request->has('amount') && $user->hasRole('admin')) {
            $sub->amount = $request->input('amount');
        }
        if ($request->has('total_visits') && $user->hasRole('admin')) {
            $sub->total_visits = $request->input('total_visits');
        }
        if ($request->has('completed_visits') && $user->hasRole('admin')) {
            $sub->completed_visits = $request->input('completed_visits');
        }
        if ($request->has('payment_reference') && $user->hasRole('admin')) {
            $sub->payment_reference = $request->input('payment_reference');
        }
        if ($request->has('plan_name') && $user->hasRole('admin')) {
            $sub->plan_name = $request->input('plan_name');
        }
        if ($request->has('subtitle') && $user->hasRole('admin')) {
            $sub->subtitle = $request->input('subtitle');
        }
        if ($request->has('features') && $user->hasRole('admin')) {
            $sub->features = $request->input('features');
        }

        $sub->save();

        return ApiResponse::success('Subscription updated successfully.', $sub->load('visits'));
    }

    /**
     * Delete/Cancel subscription
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $sub = Subscription::findOrFail($id);

        // Only admins or the owner can delete
        if (!($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $sub->delete();

        return ApiResponse::success('Subscription cancelled successfully.');
    }

    /**
     * Mark a subscription as paid (simple endpoint / webhook stub).
     */
    public function markPaid(Request $request, $id)
    {
        $user = $request->user();
        $sub = Subscription::findOrFail($id);

        // Only admins or the owner can mark as paid
        if (! ($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $sub->payment_status = 'paid';
        $sub->save();

        // Notify client that payment received
        try {
            $sub->client->notify(new \App\Notifications\SubscriptionPaid($sub));
        } catch (\Throwable $e) {
            // Log error if necessary
            // \Log::error('Failed to send payment notification: '.$e->getMessage());
        }

        return ApiResponse::success('Subscription marked as paid.', $sub);
    }
}
