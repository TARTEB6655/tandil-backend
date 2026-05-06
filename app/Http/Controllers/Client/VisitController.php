<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
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
