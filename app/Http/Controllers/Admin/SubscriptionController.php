<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = Subscription::with('client');

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('client', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('payment_status') && $request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by plan
        if ($request->has('plan') && $request->plan) {
            $query->where('plan', $request->plan);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.subscriptions.index', compact('subscriptions'));
    }

    public function show($id)
    {
        $subscription = Subscription::with(['client', 'visits.technician', 'visits.report'])
            ->findOrFail($id);

        return view('admin.subscriptions.show', compact('subscription'));
    }

    public function edit($id)
    {
        $subscription = Subscription::with('client')->findOrFail($id);
        return view('admin.subscriptions.edit', compact('subscription'));
    }

    public function update(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'payment_status' => 'required|in:pending,paid,failed,refunded,cancelled',
            'amount' => 'required|numeric|min:0',
            'total_visits' => 'nullable|integer|min:0',
            'completed_visits' => 'nullable|integer|min:0',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $subscription->update($validated);

        return redirect()->route('admin.subscriptions.show', $subscription)
            ->with('success', 'Subscription updated successfully.');
    }

    public function extend(Request $request, $id)
    {
        $request->validate([
            'months' => 'required|integer|min:1|max:12',
        ]);

        $subscription = Subscription::findOrFail($id);
        $endDate = \Carbon\Carbon::parse($subscription->end_date);
        $subscription->end_date = $endDate->addMonths($request->months);
        $subscription->save();

        return redirect()->back()->with('success', 'Subscription extended successfully');
    }

    public function activate($id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->payment_status = 'paid';
        $subscription->save();

        return redirect()->back()->with('success', 'Subscription activated successfully');
    }

    public function deactivate($id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->payment_status = 'cancelled';
        $subscription->save();

        return redirect()->back()->with('success', 'Subscription deactivated successfully');
    }
}
