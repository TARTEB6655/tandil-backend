<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        
        $visits = Visit::whereIn('subscription_id', $subscriptionIds)
            ->with(['technician', 'supervisor', 'subscription', 'area', 'photos'])
            ->orderBy('scheduled_date', 'desc')
            ->paginate(10);

        return view('client.visits.index', compact('visits'));
    }

    public function show($id)
    {
        $user = Auth::user();
        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        
        $visit = Visit::whereIn('subscription_id', $subscriptionIds)
            ->with(['technician', 'supervisor', 'subscription', 'area', 'photos', 'report'])
            ->findOrFail($id);

        return view('client.visits.show', compact('visit'));
    }
}
