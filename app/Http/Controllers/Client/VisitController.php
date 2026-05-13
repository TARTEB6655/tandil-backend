<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use App\Support\VisitAreaResolver;
use App\Models\Subscription;
use App\Models\Area;
use Illuminate\Support\Facades\Auth;
use App\Services\VisitOfferService;

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

    public function create()
    {
        $user = Auth::user();
        $subscriptions = Subscription::query()
            ->where('client_id', $user->id)
            ->orderByDesc('id')
            ->get(['id', 'plan', 'payment_status']);

        $areas = Area::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'country']);

        return view('client.visits.create', compact('subscriptions', 'areas'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'scheduled_date' => 'required|date',
            'status' => 'nullable|string|in:pending,scheduled,in_progress,completed,approved,rejected',
            'notes' => 'nullable|string|max:5000',
            'price' => 'nullable|numeric|min:0',
            'area_id' => 'nullable|exists:areas,id',
            'full_name' => 'nullable|string|max:255',
            'street_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $subscription = Subscription::query()
            ->where('id', (int) $validated['subscription_id'])
            ->where('client_id', $user->id)
            ->first();
        if (! $subscription) {
            return back()->withErrors(['subscription_id' => 'Invalid subscription selected.'])->withInput();
        }

        $resolved = $this->resolveAreaFromInput($validated);
        if (! $resolved) {
            return back()->withErrors(['city' => 'Unable to resolve area from selected location.'])->withInput();
        }

        $notes = (string) ($validated['notes'] ?? '');
        $addressLine = trim(collect([
            $validated['full_name'] ?? null,
            $validated['street_address'] ?? null,
            $validated['city'] ?? null,
            $validated['state'] ?? null,
            $validated['zip_code'] ?? null,
            $validated['country'] ?? null,
        ])->filter(fn ($v) => filled($v))->implode(', '));
        if ($addressLine !== '' && ! str_contains($notes, 'Address:')) {
            $notes = trim($notes . ($notes !== '' ? "\n" : '') . 'Address: ' . $addressLine);
        }

        $visit = Visit::create([
            'subscription_id' => $subscription->id,
            'area_id' => (int) $resolved['area']->id,
            'supervisor_id' => (int) $resolved['supervisor_id'],
            'scheduled_date' => $validated['scheduled_date'],
            'status' => $validated['status'] ?? 'pending',
            'notes' => $notes !== '' ? $notes : null,
            'price' => array_key_exists('price', $validated) && $validated['price'] !== null ? (float) $validated['price'] : null,
        ]);

        if ($visit->area_id && ! $visit->technician_id) {
            try {
                $next = VisitOfferService::findNextTechnician($visit);
                if ($next) {
                    VisitOfferService::offerToTechnician($visit, $next->id);
                } else {
                    $visit->escalated_at = now();
                    $visit->save();
                }
            } catch (\Throwable $e) {
                \Log::warning('Client visit create auto-dispatch failed: ' . $e->getMessage());
            }
        }

        return redirect()
            ->route('client.visits.index')
            ->with('success', 'Job created successfully and routed to the area supervisor.');
    }

    private function resolveAreaFromInput(array $payload): ?array
    {
        return VisitAreaResolver::resolve($payload);
    }
}
