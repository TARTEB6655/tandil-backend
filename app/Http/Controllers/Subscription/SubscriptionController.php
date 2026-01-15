<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\Visit;
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
            $subs = Subscription::with('client')->get();
        } else {
            // Clients only see their own subscriptions
            $subs = Subscription::where('client_id', $user->id)->with('visits')->get();
        }

        return ApiResponse::success('Subscriptions retrieved successfully.', $subs);
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
        $data = $request->validated();
        
        // Set client_id: admins can set it, others use their own ID
        if (!isset($data['client_id'])) {
            $data['client_id'] = $user->id;
        } elseif (!$user->hasRole('admin')) {
            // Non-admins cannot set client_id to another user
            $data['client_id'] = $user->id;
        }

        // Determine start_date (default to today if missing)
        $start = isset($data['start_date']) && $data['start_date']
            ? Carbon::parse($data['start_date'])
            : Carbon::today();
        $data['start_date'] = $start->toDateString();

        // Compute end_date based on plan length (if not provided)
        if (!isset($data['end_date']) || !$data['end_date']) {
            $planMap = [
                '1_month' => 1,
                '3_month' => 3,
                '6_month' => 6,
                '12_month' => 12,
            ];
            $months = $planMap[$data['plan']] ?? 1;
            // End date is last day of the last month of subscription
            $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
            $data['end_date'] = $end->toDateString();
        } else {
            $data['end_date'] = Carbon::parse($data['end_date'])->toDateString();
        }

        // Set default payment_status if not provided
        if (!isset($data['payment_status'])) {
            $data['payment_status'] = 'pending';
        }

        // Load prices from config (fallback to hardcoded price if config missing)
        if (!isset($data['amount']) || empty($data['amount'])) {
            $planMap = [
                '1_month' => 1,
                '3_month' => 3,
                '6_month' => 6,
                '12_month' => 12,
            ];
            $months = $planMap[$data['plan']] ?? 1;
            $plansConfig = config('subscriptions.plans', []);
            $data['amount'] = isset($plansConfig[$data['plan']]['price'])
                ? (float) $plansConfig[$data['plan']]['price']
                : (500.00 * $months);
        }

        // Set total_visits if not provided (default to plan months)
        if (!isset($data['total_visits']) || $data['total_visits'] === null) {
            $planMap = [
                '1_month' => 1,
                '3_month' => 3,
                '6_month' => 6,
                '12_month' => 12,
            ];
            $data['total_visits'] = $planMap[$data['plan']] ?? 1;
        }

        // Set completed_visits default to 0 if not provided
        if (!isset($data['completed_visits']) || $data['completed_visits'] === null) {
            $data['completed_visits'] = 0;
        }

        // Set paid_at if payment_status is paid and paid_at is not set
        if ($data['payment_status'] === 'paid' && !isset($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        $sub = Subscription::create($data);

        // Generate visits synchronously based on total_visits
        $visits = [];
        $totalVisits = $data['total_visits'];
        
        // Calculate visit interval based on subscription duration
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $daysDiff = $startDate->diffInDays($endDate);
        $visitInterval = $totalVisits > 1 ? floor($daysDiff / ($totalVisits - 1)) : 0;
        
        for ($i = 0; $i < $totalVisits; $i++) {
            if ($i === 0) {
                $scheduled = $startDate->toDateString();
            } else {
                $scheduled = $startDate->copy()->addDays($visitInterval * $i)->toDateString();
                // Ensure scheduled date doesn't exceed end_date
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

        // Reload subscription with visits
        $sub->load('visits');

        // Notify the client (database + mail) that subscription created
        try {
            $user->notify(new \App\Notifications\SubscriptionCreated($sub));
        } catch (\Throwable $e) {
            // Log error here if needed, but don't break the flow
            // \Log::error('Failed to send subscription notification: '.$e->getMessage());
        }

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
            'payment_status' => 'nullable|string|in:pending,paid,failed,refunded',
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
