<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Visit;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        $visitIds = Visit::whereIn('subscription_id', $subscriptionIds)->pluck('id');
        
        $reports = Report::whereIn('visit_id', $visitIds)
            ->with(['visit.subscription', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('client.reports.index', compact('reports'));
    }

    public function show($id)
    {
        $user = Auth::user();
        $subscriptionIds = Subscription::where('client_id', $user->id)->pluck('id');
        $visitIds = Visit::whereIn('subscription_id', $subscriptionIds)->pluck('id');
        
        $report = Report::whereIn('visit_id', $visitIds)
            ->with(['visit.subscription', 'visit.technician', 'visit.area', 'visit.photos', 'supervisor'])
            ->findOrFail($id);

        return view('client.reports.show', compact('report'));
    }
}
