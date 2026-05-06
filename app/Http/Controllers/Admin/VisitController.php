<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Area;
use App\Models\User;
use App\Models\Subscription;
use App\Services\VisitOfferService;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = Visit::with(['subscription.client', 'technician', 'area', 'report']);

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('subscription.client', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by area
        if ($request->has('area_id') && $request->area_id) {
            $query->where('area_id', $request->area_id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('scheduled_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('scheduled_date', '<=', $request->date_to);
        }

        $visits = $query->orderBy('scheduled_date', 'desc')->paginate(15);
        $areas = Area::all();

        return view('admin.visits.index', compact('visits', 'areas'));
    }

    public function show($id)
    {
        $visit = Visit::with([
            'subscription.client',
            'technician',
            'supervisor',
            'area',
            'photos',
            'report',
            'complaints'
        ])->findOrFail($id);

        return view('admin.visits.show', compact('visit'));
    }

    public function create()
    {
        $subscriptions = Subscription::query()
            ->with('client:id,name,email')
            ->orderByDesc('id')
            ->get(['id', 'client_id', 'plan', 'payment_status']);

        $areas = Area::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'country']);

        return view('admin.visits.create', compact('subscriptions', 'areas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'scheduled_date' => 'required|date',
            'status' => 'nullable|string|in:pending,scheduled,in_progress,completed,approved,rejected',
            'notes' => 'nullable|string|max:5000',
            'price' => 'nullable|numeric|min:0',
            'technician_id' => 'nullable|exists:users,id',
            'supervisor_id' => 'nullable|exists:users,id',
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

        $subscription = Subscription::query()->find((int) $validated['subscription_id']);
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
            'technician_id' => $validated['technician_id'] ?? null,
            'supervisor_id' => $validated['supervisor_id'] ?? (int) $resolved['supervisor_id'],
            'area_id' => (int) $resolved['area']->id,
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
                \Log::warning('Admin visit create auto-dispatch failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('admin.visits.index')->with('success', 'Job created successfully.');
    }

    public function assignTechnician(Request $request, $id)
    {
        $request->validate([
            'technician_id' => 'required|exists:users,id',
        ]);

        $visit = Visit::findOrFail($id);
        $technician = User::findOrFail($request->technician_id);

        if ($technician->role !== 'technician') {
            return redirect()->back()->with('error', 'Selected user is not a technician');
        }

        $visit->technician_id = $request->technician_id;
        $visit->save();

        return redirect()->back()->with('success', 'Technician assigned successfully');
    }

    public function assignSupervisor(Request $request, $id)
    {
        $request->validate([
            'supervisor_id' => 'required|exists:users,id',
        ]);

        $visit = Visit::findOrFail($id);
        $supervisor = User::findOrFail($request->supervisor_id);

        if ($supervisor->role !== 'supervisor') {
            return redirect()->back()->with('error', 'Selected user is not a supervisor');
        }

        $visit->supervisor_id = $request->supervisor_id;
        $visit->save();

        return redirect()->back()->with('success', 'Supervisor assigned successfully');
    }

    private function resolveAreaFromInput(array $payload): ?array
    {
        if (!empty($payload['area_id'])) {
            $area = Area::with('supervisors')
                ->where('is_active', true)
                ->find((int) $payload['area_id']);
            if ($area && $area->supervisors->isNotEmpty()) {
                return ['area' => $area, 'supervisor_id' => (int) $area->supervisors->first()->id];
            }
        }

        $country = strtolower(trim((string) ($payload['country'] ?? 'UAE')));
        $city = strtolower(trim((string) ($payload['city'] ?? '')));
        $state = strtolower(trim((string) ($payload['state'] ?? '')));
        $streetAddress = strtolower(trim((string) ($payload['street_address'] ?? '')));
        $zipCode = strtolower(trim((string) ($payload['zip_code'] ?? '')));
        $lat = array_key_exists('latitude', $payload) && $payload['latitude'] !== null ? (float) $payload['latitude'] : null;
        $lng = array_key_exists('longitude', $payload) && $payload['longitude'] !== null ? (float) $payload['longitude'] : null;

        $areas = Area::with('supervisors')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Area $a) => $a->supervisors->isNotEmpty())
            ->values();

        $normalizedCountry = $this->normalizeCountry($country);
        if ($normalizedCountry !== '') {
            $countryMatched = $areas->filter(function (Area $area) use ($normalizedCountry) {
                return $this->normalizeCountry((string) ($area->country ?? '')) === $normalizedCountry;
            })->values();
            if ($countryMatched->isNotEmpty()) {
                $areas = $countryMatched;
            }
        }

        $matched = $areas->first(function (Area $a) use ($city, $state, $streetAddress, $zipCode) {
            if ($city === '' && $state === '' && $streetAddress === '' && $zipCode === '') {
                return false;
            }
            $hay = strtolower(trim((string) ($a->name . ' ' . ($a->location ?? '') . ' ' . ($a->description ?? ''))));
            return ($city !== '' && str_contains($hay, $city))
                || ($state !== '' && str_contains($hay, $state))
                || ($streetAddress !== '' && str_contains($hay, $streetAddress))
                || ($zipCode !== '' && str_contains($hay, $zipCode));
        });
        if ($matched) {
            return ['area' => $matched, 'supervisor_id' => (int) $matched->supervisors->first()->id];
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        $closest = null;
        foreach ($areas as $area) {
            if ($area->latitude === null || $area->longitude === null) {
                continue;
            }
            $distance = $this->distanceKm($lat, $lng, (float) $area->latitude, (float) $area->longitude);
            $radius = max(0.1, (float) ($area->service_radius_km ?? 30));
            if ($distance > $radius) {
                continue;
            }

            if ($closest === null || $distance < $closest['distance']) {
                $closest = ['area' => $area, 'distance' => $distance];
            }
        }

        if (! $closest) {
            return null;
        }

        return [
            'area' => $closest['area'],
            'supervisor_id' => (int) $closest['area']->supervisors->first()->id,
        ];
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }

    private function normalizeCountry(string $country): string
    {
        $raw = strtolower(trim($country));
        return match ($raw) {
            'uae', 'u.a.e', 'ae', 'united arab emirates' => 'uae',
            default => $raw,
        };
    }
}
