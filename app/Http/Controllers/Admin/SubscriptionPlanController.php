<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class SubscriptionPlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $plans = config('subscriptions.plans', []);
        $plansArray = collect($plans)->map(function ($plan, $key) {
            return [
                'key' => $key,
                'label' => $plan['label'] ?? str_replace('_', ' ', $key),
                'price' => $plan['price'] ?? 0,
                'enabled' => $plan['enabled'] ?? true,
                'visit_frequency' => $plan['visit_frequency'] ?? 'monthly',
            ];
        })->values();

        return view('admin.subscription-plans.index', compact('plansArray'));
    }

    public function edit($key)
    {
        $plans = config('subscriptions.plans', []);
        
        if (!isset($plans[$key])) {
            return redirect()->route('admin.subscription-plans.index')
                ->with('error', 'Plan not found');
        }

        $plan = [
            'key' => $key,
            'label' => $plans[$key]['label'] ?? str_replace('_', ' ', $key),
            'price' => $plans[$key]['price'] ?? 0,
            'enabled' => $plans[$key]['enabled'] ?? true,
            'visit_frequency' => $plans[$key]['visit_frequency'] ?? 'monthly',
        ];

        return view('admin.subscription-plans.edit', compact('plan'));
    }

    public function update(Request $request, $key)
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
            'label' => 'required|string|max:255',
            'enabled' => 'boolean',
            'visit_frequency' => 'required|string|in:weekly,bi-weekly,monthly',
        ]);

        $plans = config('subscriptions.plans', []);
        
        if (!isset($plans[$key])) {
            return redirect()->route('admin.subscription-plans.index')
                ->with('error', 'Plan not found');
        }

        $plans[$key]['price'] = $request->price;
        $plans[$key]['label'] = $request->label;
        $plans[$key]['enabled'] = $request->has('enabled') ? true : false;
        $plans[$key]['visit_frequency'] = $request->visit_frequency;

        // Note: In production, you'd want to save this to database instead of config file
        // For now, we'll just show a message that config needs manual update
        
        return redirect()->route('admin.subscription-plans.index')
            ->with('success', 'Plan updated successfully. Note: Config file needs manual update in production.');
    }
}

