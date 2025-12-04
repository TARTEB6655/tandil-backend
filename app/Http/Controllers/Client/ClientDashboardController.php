<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ClientDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * Show the client dashboard.
     */
    public function index(): View
    {
        $user = Auth::user();

        // Example: client's subscriptions and recent reports
        $subscriptions = \App\Models\Subscription::where('user_id', $user->id)->get();
        $recentReports = \App\Models\Report::where('user_id', $user->id)->latest()->take(6)->get();

        return view('client.dashboard', compact('subscriptions', 'recentReports'));
    }
}
