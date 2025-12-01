<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Jobs\GenerateVisitsForSubscription;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            $subs = Subscription::with('client')->get();
        } else if ($user) {
            $subs = Subscription::where('client_id', $user->id)->with('visits')->get();
        } else {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json(['status' => true, 'data' => $subs], 200);
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

        return response()->json(['status' => true, 'data' => $plans], 200);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $sub = Subscription::with('visits')->find($id);

        if (! $sub) {
            return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
        }

        if (! ($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['status' => true, 'data' => $sub], 200);
    }

    public function store(StoreSubscriptionRequest $request)
    {

        $user = $request->user();

        $data = $request->validated();
        $data['client_id'] = $user->id;

        // Determine start_date (first day = when user applies)
        $start = isset($data['start_date']) && $data['start_date'] ? Carbon::parse($data['start_date']) : Carbon::today();
        $data['start_date'] = $start->toDateString();

        // Compute end_date based on plan length
        $planMap = [
            '1_month' => 1,
            '3_month' => 3,
            '6_month' => 6,
            '12_month' => 12,
        ];
        $months = $planMap[$data['plan']] ?? 1;

        // Load prices from config/subscriptions.php
        $plansConfig = config('subscriptions.plans', []);

        if (empty($data['amount'])) {
            $data['amount'] = isset($plansConfig[$data['plan']]['price']) ? (float) $plansConfig[$data['plan']]['price'] : 0.00;
        }
        // end_date is last day of last month
        $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
        $data['end_date'] = $end->toDateString();

        $data['total_visits'] = $months;

        $sub = Subscription::create($data);

        // Generate visits asynchronously; worker will create Visit records
        GenerateVisitsForSubscription::dispatch($sub);

        // reload subscription with visits
        $sub->load('visits');

        // Notify the client (database + mail) that subscription created
        if ($user) {
            try {
                $user->notify(new \App\Notifications\SubscriptionCreated($sub));
            } catch (\Throwable $e) {
                // don't break response if notification fails; log if needed
            }
        }

        return response()->json(['status' => true, 'data' => $sub], 201);
    }

    /**
     * Mark a subscription as paid (simple endpoint / webhook stub).
     */
    public function markPaid(Request $request, $id)
    {
        $user = $request->user();
        $sub = Subscription::find($id);
        if (! $sub) {
            return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
        }

        // Only admins or owner can mark paid
        if (! ($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $sub->payment_status = 'paid';
        $sub->save();

        // Notify client that payment received
        try {
            $sub->client->notify(new \App\Notifications\SubscriptionCreated($sub));
        } catch (\Throwable $e) {
        }

        return response()->json(['status' => true, 'data' => $sub], 200);
    }
}

