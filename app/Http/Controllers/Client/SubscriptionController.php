<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $subscriptions = Subscription::where('client_id', $user->id)
            ->with(['visits.technician', 'visits.supervisor', 'visits.area'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('client.subscriptions.index', compact('subscriptions'));
    }

    public function show($id)
    {
        $user = Auth::user();
        $subscription = Subscription::where('client_id', $user->id)
            ->with(['visits.technician', 'visits.supervisor', 'visits.area', 'visits.photos', 'visits.report', 'client'])
            ->findOrFail($id);

        return view('client.subscriptions.show', compact('subscription'));
    }
}
