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
    public function __construct()
    {
        // Only authenticated users with permission to manage subscriptions
        $this->middleware(['auth', 'permission:manage subscriptions']);
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

        // Determine start_date (default to today if missing)
        $start = isset($data['start_date']) && $data['start_date']
            ? Carbon::parse($data['start_date'])
            : Carbon::today();
        $data['start_date'] = $start->toDateString();

        // Compute end_date based on plan length
        $planMap = [
            '1_month' => 1,
            '3_month' => 3,
            '6_month' => 6,
            '12_month' => 12,
        ];
        $months = $planMap[$data['plan']] ?? 1;

        // Load prices from config (fallback to hardcoded price if config missing)
        $plansConfig = config('subscriptions.plans', []);
        if (empty($data['amount'])) {
            $data['amount'] = isset($plansConfig[$data['plan']]['price'])
                ? (float) $plansConfig[$data['plan']]['price']
                : (500.00 * $months);
        }

        // End date is last day of the last month of subscription
        $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
        $data['end_date'] = $end->toDateString();

        $data['total_visits'] = $months;

        $sub = Subscription::create($data);

        // Generate visits asynchronously; worker will create Visit records
        GenerateVisitsForSubscription::dispatch($sub);

        // Reload subscription with visits
        $sub->load('visits');

        // Notify the client (database + mail) that subscription created
        try {
            $user->notify(new \App\Notifications\SubscriptionCreated($sub));
        } catch (\Throwable $e) {
            // Log error here if needed, but don't break the flow
            // \Log::error('Failed to send subscription notification: '.$e->getMessage());
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

        // Only admins or the owner can mark as paid
        if (! ($user->hasRole('admin') || $sub->client_id == $user->id)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
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

        return response()->json(['status' => true, 'data' => $sub], 200);
    }
}
